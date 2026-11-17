<?php

/*
|--------------------------------------------------------------------------
| Service routes of the package
|--------------------------------------------------------------------------
|
| Interface language, authentication (only when `ecms.auth_routes` is on), the admin panel under the fixed
| prefix `admin`, and the image style route. Loaded by Webtack\Ecms\EcmsServiceProvider inside the `web`
| middleware group, always. The public site is in `routes/public.php`.
|
*/

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Webtack\Ecms\Files\ImageStyles;
use Webtack\Ecms\Http\Controllers\Admin\ContentFieldController;
use Webtack\Ecms\Http\Controllers\Admin\ContentLanguageController;
use Webtack\Ecms\Http\Controllers\Admin\ContentNodeController;
use Webtack\Ecms\Http\Controllers\Admin\ContentTypeController;
use Webtack\Ecms\Http\Controllers\Admin\ContentTypeDisplayController;
use Webtack\Ecms\Http\Controllers\Admin\ContentTypeSitemapController;
use Webtack\Ecms\Http\Controllers\Admin\ContentTypeValuesController;
use Webtack\Ecms\Http\Controllers\Admin\ContentTypeValuesTranslationController;
use Webtack\Ecms\Http\Controllers\Admin\DashboardController;
use Webtack\Ecms\Http\Controllers\Admin\FavoriteTypeController;
use Webtack\Ecms\Http\Controllers\Admin\FileController;
use Webtack\Ecms\Http\Controllers\Admin\MediaController;
use Webtack\Ecms\Http\Controllers\Admin\MenuController;
use Webtack\Ecms\Http\Controllers\Admin\MenuItemController;
use Webtack\Ecms\Http\Controllers\Admin\NavigationMenuController;
use Webtack\Ecms\Http\Controllers\Admin\ProfileController;
use Webtack\Ecms\Http\Controllers\Admin\ReferenceLookupController;
use Webtack\Ecms\Http\Controllers\Admin\RoleController;
use Webtack\Ecms\Http\Controllers\Admin\SiteSettingsController;
use Webtack\Ecms\Http\Controllers\Admin\TermController;
use Webtack\Ecms\Http\Controllers\Admin\Ui\DataTableDemoController;
use Webtack\Ecms\Http\Controllers\Admin\Ui\FileUploadDemoController;
use Webtack\Ecms\Http\Controllers\Admin\UserController;
use Webtack\Ecms\Http\Controllers\Admin\VocabularyController;
use Webtack\Ecms\Http\Controllers\Admin\YoutubeLookupController;
use Webtack\Ecms\Http\Controllers\Auth\LoginController;
use Webtack\Ecms\Http\Controllers\Auth\LogoutController;
use Webtack\Ecms\Http\Controllers\Auth\VerifyEmailNoticeController;
use Webtack\Ecms\Http\Controllers\ImageStyleController;
use Webtack\Ecms\Http\Controllers\LocaleController;
use Webtack\Ecms\Http\Middleware\EnsureUserIsActive;
use Webtack\Ecms\Http\Middleware\HandleInertiaRequests;
use Webtack\Ecms\Http\Middleware\SetLocale;
use Webtack\Ecms\Models\AdminMenuEntry;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;

Route::post('/locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

// Authentication routes: skipped when the host has its own login (`ecms.auth_routes` is false).
if (config('ecms.auth_routes')) {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', LogoutController::class)->name('logout');
        Route::get('/email/verify', VerifyEmailNoticeController::class)->name('verification.notice');
    });
}

// Type routes address a type by `key` and only within the kind their section serves (the route's
// `kind` default, see the loop below); a key of another kind answers 404.
Route::bind('contentType', fn (string $key, RoutingRoute $route) => ContentType::query()
    ->ofKind($route->defaults['kind'])
    ->where('key', $key)
    ->firstOrFail());

// Field routes address a field by the pair (type, `key`); a key of another type or a missing one answers 404.
Route::bind('field', fn (string $key, RoutingRoute $route) => ContentField::query()
    ->where('content_type', $route->parameter('contentType')->key)
    ->where('key', $key)
    ->firstOrFail());

// Node routes address a node by the pair (type, `uuid`); an unknown uuid or one of another type answers 404.
Route::bind('node', fn (string $uuid, RoutingRoute $route) => ContentNode::query()
    ->where('content_type', $route->parameter('contentType')->key)
    ->where('uuid', $uuid)
    ->firstOrFail());

// An admin menu entry (group or link) is addressed by its numeric id.
Route::bind('entry', fn (string $id) => AdminMenuEntry::query()->findOrFail($id));

// A vocabulary is addressed by its immutable `key` (it has no uuid); an unknown key answers 404.
Route::bind('vocabulary', fn (string $key) => Vocabulary::query()->where('key', $key)->firstOrFail());

// Term routes address a term by the pair (vocabulary, `uuid`); an unknown uuid or one of another vocabulary answers 404.
Route::bind('term', fn (string $uuid, RoutingRoute $route) => Term::query()
    ->where('vocabulary_key', $route->parameter('vocabulary')->key)
    ->where('uuid', $uuid)
    ->firstOrFail());

// A content language is addressed by its normalised `code`; an unknown code answers 404.
Route::bind('language', fn (string $code) => AppLanguage::query()->where('code', $code)->firstOrFail());

// A menu is addressed by its immutable `key` (it has no uuid); an unknown key answers 404.
Route::bind('menu', fn (string $key) => Menu::query()->where('key', $key)->firstOrFail());

// Item routes address an item by the pair (menu, `uuid`); an unknown uuid or one of another menu answers 404.
Route::bind('item', fn (string $uuid, RoutingRoute $route) => MenuItem::query()
    ->where('menu_key', $route->parameter('menu')->key)
    ->where('uuid', $uuid)
    ->firstOrFail());

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // One set of routes per kind: `/admin/content-types/**` serves `content`, `/admin/page-types/**` serves `page`,
    // `/admin/block-types/**` serves `block`.
    // Route groups cannot carry defaults, so each route gets its `kind` here.
    $typeRoutes = [
        ['get', '/', [ContentTypeController::class, 'index'], 'index'],
        ['get', '/create', [ContentTypeController::class, 'create'], 'create'],
        ['post', '/', [ContentTypeController::class, 'store'], 'store'],
        ['get', '/{contentType}/edit', [ContentTypeController::class, 'edit'], 'edit'],
        ['put', '/{contentType}', [ContentTypeController::class, 'update'], 'update'],
        ['delete', '/{contentType}', [ContentTypeController::class, 'destroy'], 'destroy'],
        ['post', '/{contentType}/clone', [ContentTypeController::class, 'clone'], 'clone'],
        ['get', '/{contentType}/display', [ContentTypeController::class, 'displayTab'], 'display'],
        ['put', '/{contentType}/display', [ContentTypeDisplayController::class, 'update'], 'display.update'],
        ['get', '/{contentType}/children', [ContentTypeController::class, 'children'], 'children'],
        ['get', '/{contentType}/fields', [ContentFieldController::class, 'index'], 'fields.index'],
        ['get', '/{contentType}/fields/create', [ContentFieldController::class, 'create'], 'fields.create'],
        ['post', '/{contentType}/fields', [ContentFieldController::class, 'store'], 'fields.store'],
        // Declared before the `{field}` routes so that `order` is never bound as a field key.
        ['put', '/{contentType}/fields/order', [ContentFieldController::class, 'reorder'], 'fields.reorder'],
        ['get', '/{contentType}/fields/{field}/edit', [ContentFieldController::class, 'edit'], 'fields.edit'],
        ['put', '/{contentType}/fields/{field}', [ContentFieldController::class, 'update'], 'fields.update'],
        ['delete', '/{contentType}/fields/{field}', [ContentFieldController::class, 'destroy'], 'fields.destroy'],
        ['put', '/{contentType}/fields/{field}/translations/{lang}', [ContentFieldController::class, 'saveTranslation'], 'fields.translations.save'],
        ['delete', '/{contentType}/fields/{field}/translations/{lang}', [ContentFieldController::class, 'clearTranslation'], 'fields.translations.clear'],
    ];

    foreach (['content-types' => 'content', 'page-types' => 'page', 'block-types' => 'block'] as $section => $kind) {
        foreach ($typeRoutes as [$method, $uri, $action, $name]) {
            Route::$method(rtrim("/$section".($uri === '/' ? '' : $uri), '/'), $action)
                ->name("$section.$name")
                // The list of types is also the way to the nodes: `ContentTypeController::index` lets in who
                // may write nodes of the kind (and shows only those types); everything else needs `types`.
                ->middleware($name === 'index' ? [] : ['can:types'])
                ->defaults('kind', $kind);
        }
    }
    // The "SEO" tab (values of the type itself and the "Meta data" choice, saved by `values.update`) and the "Sitemap" tab
    // (`settings.sitemap`) exist for content and page types; `block` answers 404.
    foreach (['content-types' => 'content', 'page-types' => 'page'] as $section => $kind) {
        Route::get("/$section/{contentType}/seo", [ContentTypeController::class, 'seoTab'])
            ->name("$section.seo")
            ->middleware('can:types')
            ->defaults('kind', $kind);
        Route::get("/$section/{contentType}/sitemap", [ContentTypeSitemapController::class, 'edit'])
            ->name("$section.sitemap")
            ->middleware('can:types')
            ->defaults('kind', $kind);
        Route::put("/$section/{contentType}/sitemap", [ContentTypeSitemapController::class, 'update'])
            ->name("$section.sitemap.update")
            ->middleware('can:types')
            ->defaults('kind', $kind);
        Route::put("/$section/{contentType}/values", [ContentTypeValuesController::class, 'update'])
            ->name("$section.values.update")
            ->middleware('can:types')
            ->defaults('kind', $kind);
    }
    // Translations of the values of the tabs (`panel` = `section` for "Display", `seo` for "SEO"); a type without values in
    // the panel (every `block`, a `page` on "Display") answers 404.
    foreach (['content-types' => 'content', 'page-types' => 'page', 'block-types' => 'block'] as $section => $kind) {
        Route::put("/$section/{contentType}/values/translations/{lang}", [ContentTypeValuesTranslationController::class, 'save'])
            ->name("$section.values.translations.save")
            ->middleware('can:types')
            ->defaults('kind', $kind);
        Route::delete("/$section/{contentType}/values/translations/{lang}", [ContentTypeValuesTranslationController::class, 'clear'])
            ->name("$section.values.translations.clear")
            ->middleware('can:types')
            ->defaults('kind', $kind);
    }
    // The same node routes in every section; the kind (and so the type binding) comes from the `kind` default.
    // A node is addressed by its immutable `uuid`, never by `slug` (the public identifier of the site).
    $nodeRoutes = [
        ['get', '/', [ContentNodeController::class, 'index'], 'index'],
        ['get', '/create', [ContentNodeController::class, 'create'], 'create'],
        ['post', '/', [ContentNodeController::class, 'store'], 'store'],
        ['get', '/{node}/edit', [ContentNodeController::class, 'edit'], 'edit'],
        ['put', '/{node}', [ContentNodeController::class, 'update'], 'update'],
        ['delete', '/{node}', [ContentNodeController::class, 'destroy'], 'destroy'],
        ['patch', '/{node}/active', [ContentNodeController::class, 'setActive'], 'active'],
        ['put', '/{node}/translations/{lang}', [ContentNodeController::class, 'saveTranslation'], 'translations.save'],
        ['delete', '/{node}/translations/{lang}', [ContentNodeController::class, 'clearTranslation'], 'translations.clear'],
    ];

    foreach (['content-types' => 'content', 'page-types' => 'page', 'block-types' => 'block'] as $section => $kind) {
        foreach ($nodeRoutes as [$method, $uri, $action, $name]) {
            Route::$method(rtrim("/$section/{contentType}/nodes".($uri === '/' ? '' : $uri), '/'), $action)
                ->name("$section.nodes.$name")
                ->defaults('kind', $kind);
        }
    }
    // Taxonomy needs the `taxonomy` permission.
    Route::middleware('can:taxonomy')->group(function () {
        Route::get('/taxonomy', [VocabularyController::class, 'index'])->name('taxonomy.index');
        Route::get('/taxonomy/create', [VocabularyController::class, 'create'])->name('taxonomy.create');
        Route::post('/taxonomy', [VocabularyController::class, 'store'])->name('taxonomy.store');
        Route::get('/taxonomy/{vocabulary}/edit', [VocabularyController::class, 'edit'])->name('taxonomy.edit');
        Route::put('/taxonomy/{vocabulary}', [VocabularyController::class, 'update'])->name('taxonomy.update');
        Route::delete('/taxonomy/{vocabulary}', [VocabularyController::class, 'destroy'])->name('taxonomy.destroy');
        Route::get('/taxonomy/{vocabulary}/terms', [TermController::class, 'index'])->name('taxonomy.terms.index');
        Route::get('/taxonomy/{vocabulary}/terms/create', [TermController::class, 'create'])->name('taxonomy.terms.create');
        Route::post('/taxonomy/{vocabulary}/terms', [TermController::class, 'store'])->name('taxonomy.terms.store');
        // Declared before the `{term}` routes so that `order` is never bound as a term uuid.
        Route::put('/taxonomy/{vocabulary}/terms/order', [TermController::class, 'reorder'])->name('taxonomy.terms.reorder');
        Route::get('/taxonomy/{vocabulary}/terms/{term}/edit', [TermController::class, 'edit'])->name('taxonomy.terms.edit');
        Route::put('/taxonomy/{vocabulary}/terms/{term}', [TermController::class, 'update'])->name('taxonomy.terms.update');
        Route::delete('/taxonomy/{vocabulary}/terms/{term}', [TermController::class, 'destroy'])->name('taxonomy.terms.destroy');
        Route::put('/taxonomy/{vocabulary}/terms/{term}/translations/{lang}', [TermController::class, 'saveTranslation'])->name('taxonomy.terms.translations.save');
        Route::delete('/taxonomy/{vocabulary}/terms/{term}/translations/{lang}', [TermController::class, 'clearTranslation'])->name('taxonomy.terms.translations.clear');
    });
    // Menus need the `menus` permission.
    Route::middleware('can:menus')->group(function () {
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::get('/menus/create', [MenuController::class, 'create'])->name('menus.create');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])->name('menus.edit');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::get('/menus/{menu}/items', [MenuItemController::class, 'index'])->name('menus.items.index');
        Route::get('/menus/{menu}/items/create', [MenuItemController::class, 'create'])->name('menus.items.create');
        Route::post('/menus/{menu}/items', [MenuItemController::class, 'store'])->name('menus.items.store');
        // Declared before the `{item}` routes so that `order` is never bound as an item uuid.
        Route::put('/menus/{menu}/items/order', [MenuItemController::class, 'reorder'])->name('menus.items.reorder');
        Route::get('/menus/{menu}/items/{item}/edit', [MenuItemController::class, 'edit'])->name('menus.items.edit');
        Route::put('/menus/{menu}/items/{item}', [MenuItemController::class, 'update'])->name('menus.items.update');
        Route::delete('/menus/{menu}/items/{item}', [MenuItemController::class, 'destroy'])->name('menus.items.destroy');
        Route::patch('/menus/{menu}/items/{item}/active', [MenuItemController::class, 'setActive'])->name('menus.items.active');
        Route::put('/menus/{menu}/items/{item}/translations/{lang}', [MenuItemController::class, 'saveTranslation'])->name('menus.items.translations.save');
        Route::delete('/menus/{menu}/items/{item}/translations/{lang}', [MenuItemController::class, 'clearTranslation'])->name('menus.items.translations.clear');
    });
    // Roles and their permission matrix; `{role}` is the numeric id (a role has no key or uuid).
    Route::middleware('can:roles')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
    // Site settings need the `settings` permission.
    Route::middleware('can:settings')->group(function () {
        Route::get('/settings', [SiteSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SiteSettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/translations/{lang}', [SiteSettingsController::class, 'saveTranslation'])->name('settings.translations.save');
        Route::delete('/settings/translations/{lang}', [SiteSettingsController::class, 'clearTranslation'])->name('settings.translations.clear');
        // Content languages: the switch, the base code and the additional languages (addressed by code).
        Route::get('/settings/languages', [ContentLanguageController::class, 'index'])->name('settings.languages.index');
        Route::put('/settings/languages', [ContentLanguageController::class, 'updateSettings'])->name('settings.languages.settings');
        Route::post('/settings/languages', [ContentLanguageController::class, 'store'])->name('settings.languages.store');
        Route::put('/settings/languages/{language}', [ContentLanguageController::class, 'update'])->name('settings.languages.update');
        Route::delete('/settings/languages/{language}', [ContentLanguageController::class, 'destroy'])->name('settings.languages.destroy');
        // The admin menu shown to everyone; `order` is declared before the `{entry}` routes.
        Route::get('/settings/navigation', [NavigationMenuController::class, 'index'])->name('settings.navigation.index');
        Route::post('/settings/navigation/groups', [NavigationMenuController::class, 'storeGroup'])->name('settings.navigation.groups.store');
        Route::post('/settings/navigation/links', [NavigationMenuController::class, 'storeLink'])->name('settings.navigation.links.store');
        Route::put('/settings/navigation/order', [NavigationMenuController::class, 'reorder'])->name('settings.navigation.order');
        Route::put('/settings/navigation/create-types', [NavigationMenuController::class, 'updateCreateTypes'])->name('settings.navigation.create-types');
        Route::put('/settings/navigation/groups/{entry}', [NavigationMenuController::class, 'updateGroup'])->name('settings.navigation.groups.update');
        Route::delete('/settings/navigation/entries/{entry}', [NavigationMenuController::class, 'destroy'])->name('settings.navigation.entries.destroy');
    });
    // Users; `{user}` is the numeric id.
    Route::middleware('can:users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
    // The personal "Favorites" of the sidebar; open to every user, but only for a type the user may open (checked in the controller).
    Route::post('/favorites/{typeKey}', [FavoriteTypeController::class, 'store'])->name('favorites.store');
    Route::delete('/favorites/{typeKey}', [FavoriteTypeController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password');
    Route::post('/files', [FileController::class, 'store'])->middleware('throttle:upload')->name('files.store');
    // Autocomplete search of the `type_ref` / `node_ref` fields (JSON).
    Route::get('/lookup/content-types', [ReferenceLookupController::class, 'contentTypes'])->middleware('throttle:lookup')->name('lookup.content-types');
    Route::get('/lookup/nodes', [ReferenceLookupController::class, 'nodes'])->middleware('throttle:lookup')->name('lookup.nodes');
    Route::post('/youtube/lookup', YoutubeLookupController::class)->middleware('throttle:youtube')->name('youtube.lookup');
    // Files are addressed by `uuid`; the media level (`none` / `own` / `any`) is checked in `MediaController`.
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/{file:uuid}', [MediaController::class, 'show'])->name('media.show');
    Route::delete('/media/{file:uuid}', [MediaController::class, 'destroy'])->name('media.destroy');
    // Developer demo pages of shared components; not part of any role's work, so they need the `types` permission.
    Route::middleware('can:types')->group(function () {
        Route::get('/ui/data-table', DataTableDemoController::class)->name('ui.data-table');
        Route::get('/ui/file-upload', FileUploadDemoController::class)->name('ui.file-upload');
    });
});

// Lazy creation of image style files (see ImageStyleController). The path is the URL path of the disk of the
// `image` profile, so it follows the configuration. A web server serves existing files itself; only a missing
// file falls through to here. A file response needs neither the session nor the site's middleware.
$stylesPath = parse_url(Storage::disk(config('files.profiles.image.disk'))->url(ImageStyles::DIRECTORY), PHP_URL_PATH);
Route::get(rtrim((string) $stylesPath, '/').'/{style}/{file}', ImageStyleController::class)
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        PreventRequestForgery::class,
        ShareErrorsFromSession::class,
        EnsureUserIsActive::class,
        SetLocale::class,
        HandleInertiaRequests::class,
    ])
    ->name('image-styles.show');
