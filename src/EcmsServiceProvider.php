<?php

namespace Webtack\Ecms;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Console\Commands\EcmsInstallCommand;
use Webtack\Ecms\Console\Commands\ImageStylesFlushCommand;
use Webtack\Ecms\Console\Commands\ThemeCheckCommand;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\LanguageUsages\ContentValuesUsage;
use Webtack\Ecms\Content\LanguageUsages\FieldLabelsUsage;
use Webtack\Ecms\Content\LanguageUsages\FileDescriptionsUsage;
use Webtack\Ecms\Content\LanguageUsages\MenuItemTranslationsUsage;
use Webtack\Ecms\Content\LanguageUsages\SiteSettingsUsage;
use Webtack\Ecms\Content\LanguageUsages\TermTranslationsUsage;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Content\PublicLanguages;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Files\FileProfiles;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Http\Controllers\Public\NotFoundController;
use Webtack\Ecms\Http\Middleware\EnsureUserIsActive;
use Webtack\Ecms\Http\Middleware\HandleInertiaRequests;
use Webtack\Ecms\Http\Middleware\ResolvePublicLanguage;
use Webtack\Ecms\Http\Middleware\SetLocale;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Policies\ContentNodePolicy;
use Webtack\Ecms\Policies\MediaPolicy;
use Webtack\Ecms\Policies\UserPolicy;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Support\AdminAssets;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Support\HostFirstFileLoader;
use Webtack\Ecms\Support\UserModel;
use Webtack\Ecms\Theme\BlockRenderer;
use Webtack\Ecms\Theme\DisplayFields;
use Webtack\Ecms\Theme\FieldRenderer;
use Webtack\Ecms\Theme\MenuRenderer;
use Webtack\Ecms\Theme\NodeContentRenderer;
use Webtack\Ecms\Theme\PreviewRenderer;
use Webtack\Ecms\Theme\ThemeManager;
use Webtack\Ecms\Theme\TypeSection;

/**
 * Registers the CMS in a Laravel application. Discovered automatically (`extra.laravel.providers` in
 * `composer.json`), so the host edits none of its bootstrap, routing or provider files.
 *
 * `register()` merges the configuration (`content`, `files`, `theme`, `social_networks`, `limits`, `ecms`; the host
 * overrides top-level keys) and binds the services. `boot()` adds the Gate abilities and policies, the rate
 * limiters, the Blade directives, the translations, the `web` middleware, the redirects and the routes:
 *
 * - service routes (`routes/service.php`) are loaded in `boot()`;
 * - the public site (`routes/public.php`) is loaded once the application has booted, so it is registered after
 *   every route of the host, and only when `ecms.public_site` is on;
 * - the redirects to `/login` and `/admin/dashboard` are set only when `ecms.auth_routes` is on;
 * - the theme 404 page and the language prefix of public addresses exist only when `ecms.public_site` is on.
 *
 * @example
 *   // Environment of the host: ECMS_PUBLIC_SITE=false, ECMS_AUTH_ROUTES=false
 *   config('ecms.public_site'); // false: no public routes, no language prefix, the standard 404
 *   // php artisan vendor:publish --tag=ecms-config   (also: ecms-lang, ecms-views)
 */
class EcmsServiceProvider extends ServiceProvider
{
    /** The configuration files shipped by the package, each under the key of its file name. */
    private const CONFIG = ['content', 'files', 'theme', 'social_networks', 'limits', 'ecms'];

    /** The named rate limiters, used as `throttle:<name>`; the numbers come from `limits.<name>_per_minute`. */
    private const LIMITERS = ['search', 'upload', 'lookup', 'youtube'];

    /** The root directory of the package. */
    private string $root;

    public function __construct($app)
    {
        parent::__construct($app);

        $this->root = dirname(__DIR__);
    }

    /**
     * Merge the configuration and register the services.
     */
    public function register(): void
    {
        foreach (self::CONFIG as $name) {
            $this->mergeConfigFrom("{$this->root}/config/{$name}.php", $name);
        }

        // The host's JSON translations win over the package's (see HostFirstFileLoader).
        $this->app->extend('translation.loader', fn (Loader $loader, $app) => $loader instanceof FileLoader
            ? new HostFirstFileLoader($app['files'], $loader->paths(), $app->langPath())
            : $loader);

        $this->app->singleton(AccessResolver::class);
        $this->app->singleton(AppSettings::class);
        $this->app->singleton(AdminAssets::class);
        $this->app->singleton(SiteSettings::class);
        $this->app->singleton(FileProfiles::class);
        $this->app->singleton(FileStorage::class);
        $this->app->singleton(ContentLanguages::class);
        $this->app->singleton(PublicLanguages::class);
        // The reading language of one request (set by `ResolvePublicLanguage`).
        $this->app->scoped(LocalizedUrl::class);
        // Checkers asked before a content language is deleted; later features add theirs to the same tag.
        $this->app->tag([ContentValuesUsage::class, TermTranslationsUsage::class, MenuItemTranslationsUsage::class, FieldLabelsUsage::class, SiteSettingsUsage::class, FileDescriptionsUsage::class], ContentLanguages::USAGE_TAG);
        // Themes are looked up in the application's directory first, then in the package.
        $this->app->singleton(ThemeManager::class, fn () => new ThemeManager(
            [config('theme.path'), "{$this->root}/themes"],
            config('theme.active'),
            config('theme.fallback'),
        ));
        // Resolved references live for one request (or one queued job), never across them.
        $this->app->scoped(PresentContext::class);
        $this->app->scoped(DisplayFields::class);
        $this->app->scoped(FieldRenderer::class);
        $this->app->scoped(PreviewRenderer::class);
        $this->app->scoped(BlockRenderer::class);
        $this->app->scoped(NodeContentRenderer::class);
        $this->app->scoped(MenuRenderer::class);
        $this->app->scoped(TypeSection::class);
        $this->app->singleton(FieldTypeRegistry::class, fn () => new FieldTypeRegistry(config('content.field_types')));
    }

    /**
     * Bootstrap the package: access control, limits, templates, translations, middleware and routes.
     */
    public function boot(): void
    {
        $this->bootAccess();
        $this->bootRateLimiters();
        $this->bootBlade();
        $this->bootTranslations();
        $this->bootViews();
        $this->bootMiddleware();
        $this->bootRoutes();
        $this->loadMigrationsFrom("{$this->root}/database/migrations");
        $this->bootPublishing();
    }

    /** One gate per system permission, the model policies and the `{user}` route binding. */
    private function bootAccess(): void
    {
        // So routes can use `can:menus` and the like.
        foreach (AccessResolver::SYSTEM as $permission) {
            Gate::define($permission, fn (EcmsUser $user) => app(AccessResolver::class)->allows($user, $permission));
        }

        Gate::policy(ContentNode::class, ContentNodePolicy::class);
        Gate::policy(File::class, MediaPolicy::class);
        Gate::policy(UserModel::class(), UserPolicy::class);

        // The controllers type the `{user}` parameter as the contract EcmsUser, which implicit binding cannot
        // resolve (an interface is not a model), so the parameter is bound to the host's user model explicitly.
        Route::model('user', UserModel::class());
    }

    /**
     * Named limiters used as `throttle:<name>`; numbers live in `config/limits.php`.
     * The key is the user id when signed in, otherwise the IP address.
     */
    private function bootRateLimiters(): void
    {
        foreach (self::LIMITERS as $name) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute(max(1, (int) config("limits.{$name}_per_minute")))
                ->by($name.'|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        }
    }

    /** The Blade namespace `theme` and the five theme directives. */
    private function bootBlade(): void
    {
        // Only points the Blade namespace `theme` at directories; the theme itself is checked on the first public render.
        $this->app->make(ThemeManager::class)->registerViews();

        // Theme directives: thin wrappers, the template variables ($node, $type, $fields) are read from its scope.
        // @field($values, 'key') renders one field, @fields($values) every field of $fields, @fields($values, ['a', 'b']) some.
        Blade::directive('field', fn (string $expression) => '<?php echo app('.FieldRenderer::class."::class)->renderInScope(get_defined_vars(), {$expression}); ?>");
        Blade::directive('fields', fn (string $expression) => '<?php echo app('.FieldRenderer::class."::class)->renderManyInScope(get_defined_vars(), {$expression}); ?>");

        // @preview($item) renders the card of a record in a list template.
        Blade::directive('preview', fn (string $expression) => '<?php echo app('.PreviewRenderer::class."::class)->renderInScope(get_defined_vars(), {$expression}); ?>");

        // @block('block-footer') places a visible block by its slug.
        Blade::directive('block', fn (string $expression) => '<?php echo app('.BlockRenderer::class."::class)->render({$expression}); ?>");

        // @menu('main') places the visible items of a menu by its key.
        Blade::directive('menu', fn (string $expression) => '<?php echo app('.MenuRenderer::class."::class)->render({$expression}); ?>");
    }

    /**
     * The `ecms` view namespace. The Inertia root view `ecms::app` gets the admin asset tags from the package's own Vite
     * instance ({@see AdminAssets}), or null when the build is not published (the view then explains how to publish it).
     */
    private function bootViews(): void
    {
        $this->loadViewsFrom("{$this->root}/resources/views", 'ecms');

        $this->app->make('view')->composer('ecms::app', fn ($view) => $view->with('ecmsAssets', $this->app->make(AdminAssets::class)->tags()));
    }

    /**
     * Translations of the package. The JSON dictionary is read for `__('Some text')`; the PHP groups `auth` and
     * `validation` are registered without a namespace, so `__('auth.failed')` and the validator work unchanged.
     * A namespace-less path is searched after the framework's and the host's `lang/`, and a later path wins on the
     * keys both define: the package's `auth.php`/`validation.php` override the same keys of the host, while the host's
     * JSON keys win over the package's: on the server through {@see HostFirstFileLoader} (Laravel reads the JSON
     * dictionary from the same directories, so the package would otherwise win), on the client through
     * {@see InterfaceTranslations}.
     */
    private function bootTranslations(): void
    {
        $this->loadJsonTranslationsFrom("{$this->root}/lang");
        $this->loadTranslationsFrom("{$this->root}/lang");
    }

    /**
     * The `web` group middleware, the redirects of guests and signed-in users, the public 404 and the language prefix.
     */
    private function bootMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        // Ends the other sessions of a user whose password changed. A host that already did it (`authenticateSessions()`)
        // has the alias `auth.session` in the group, so the class is not added a second time.
        if (! in_array('auth.session', $router->getMiddlewareGroups()['web'] ?? [], true)) {
            $router->pushMiddlewareToGroup('web', AuthenticateSession::class);
        }

        foreach ([EnsureUserIsActive::class, SetLocale::class, HandleInertiaRequests::class] as $middleware) {
            $router->pushMiddlewareToGroup('web', $middleware);
        }

        if (config('ecms.auth_routes')) {
            // The same callbacks `Middleware::redirectTo()` sets in a host's `bootstrap/app.php`.
            $guests = fn () => '/login';
            Authenticate::redirectUsing($guests);
            AuthenticateSession::redirectUsing($guests);
            AuthenticationException::redirectUsing($guests);
            RedirectIfAuthenticated::redirectUsing(fn () => '/admin/dashboard');
        }

        if (! config('ecms.public_site')) {
            return;
        }

        // A GET that matches no route is a missing page of the public site (the theme's 404); the admin, service
        // paths, JSON and other verbs keep the standard 404 (decided by the controller).
        $this->callAfterResolving(ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'renderable')) {
                $handler->renderable(fn (NotFoundHttpException $e, Request $request) => $this->app->make(NotFoundController::class)->render($request));
            }
        });

        // Strips the language prefix of a public address before routing, so it has to be global middleware.
        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof FoundationKernel) {
            $kernel->prependMiddleware(ResolvePublicLanguage::class);
        }
    }

    /**
     * The service routes now, the public routes after every provider booted (so after the host's routes).
     */
    private function bootRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware('web')->group(fn () => $this->loadRoutesFrom("{$this->root}/routes/service.php"));

        if (config('ecms.public_site')) {
            $this->app->booted(fn () => Route::middleware('web')->group("{$this->root}/routes/public.php"));
        }
    }

    /**
     * The console commands and the publish tags `ecms-config`, `ecms-lang`, `ecms-views`, `ecms-assets` (the admin
     * build and the built assets of the theme `default`), `ecms-theme` (the reference theme `default`, sources) and
     * `ecms-theme-tooling` (Vite config and runner for building a theme in the host).
     */
    private function bootPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([EcmsInstallCommand::class, ThemeCheckCommand::class, ImageStylesFlushCommand::class]);

        $this->publishes(
            array_combine(
                array_map(fn (string $name) => "{$this->root}/config/{$name}.php", self::CONFIG),
                array_map(fn (string $name) => config_path("{$name}.php"), self::CONFIG),
            ),
            'ecms-config',
        );
        $this->publishes(["{$this->root}/lang" => $this->app->langPath()], 'ecms-lang');
        $this->publishes(["{$this->root}/resources/views" => $this->app->resourcePath('views/vendor/ecms')], 'ecms-views');

        $assets = [
            "{$this->root}/dist/build" => public_path(AdminAssets::BUILD_DIRECTORY),
            "{$this->root}/dist/themes/default" => public_path('themes/default'),
        ];

        // Static files (fonts, images) of the theme exist only when `default` has any; publishing a missing source is an error.
        if (is_dir("{$this->root}/dist/app/default")) {
            $assets["{$this->root}/dist/app/default"] = public_path('app/default');
        }

        $this->publishes($assets, 'ecms-assets');
        $this->publishes(["{$this->root}/themes/default" => $this->app->basePath('themes/default')], 'ecms-theme');
        $this->publishes([
            "{$this->root}/vite.theme.config.js" => $this->app->basePath('vite.theme.config.js'),
            "{$this->root}/scripts/theme.mjs" => $this->app->basePath('scripts/theme.mjs'),
        ], 'ecms-theme-tooling');
    }
}
