<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\MenuRenderer;
use Webtack\Ecms\Theme\ThemeManager;

/**
 * `@menu('key')` and the `default` menu template: nesting, hidden branches, a missing menu, a menu of its own.
 * The `default` theme is the real one (no fixtures) unless a test builds a fixture theme.
 */
class MenuRendererTest extends TestCase
{
    use RefreshDatabase, UsesFixtureThemes;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menu = Menu::factory()->create(['key' => 'main', 'name' => 'Main menu']);

        // The markup assertions below are about the shipped `default` theme, whatever APP_THEME says.
        config(['theme.active' => 'default', 'theme.fallback' => 'default']);
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->forgetScopedInstances();
        $this->app->make(ThemeManager::class)->registerViews();
    }

    /** @param array<string, mixed> $extra */
    private function item(string $title, ?MenuItem $parent = null, ?string $url = '/x', array $extra = [], ?Menu $menu = null): MenuItem
    {
        $item = MenuItem::factory()->in($menu ?? $this->menu)->create(['parent_uuid' => $parent?->uuid, 'url' => $url, ...$extra]);
        MenuItemTranslation::factory()->create(['item_uuid' => $item->uuid, 'lang' => 'default', 'title' => $title]);

        return $item;
    }

    private function render(string $key = 'main'): string
    {
        return app(MenuRenderer::class)->render($key);
    }

    public function test_the_candidates_are_the_menu_of_the_key_then_the_generic_one(): void
    {
        $this->assertSame(['menus/menu--footer', 'menus/menu'], app(MenuRenderer::class)->candidates('footer'));
    }

    public function test_nested_items_keep_their_hierarchy(): void
    {
        $parent = $this->item('Catalog', url: '/catalog');
        $this->item('Lamps', $parent, '/catalog/lamps');

        $html = $this->render();

        // Links may carry the theme's utility classes, so only the address and the label are matched.
        $base = preg_quote(url('/'), '#');
        $this->assertMatchesRegularExpression('#<a [^>]*href="'.$base.'/catalog"[^>]*>Catalog</a>#', $html);
        $this->assertMatchesRegularExpression('#<a [^>]*href="'.$base.'/catalog/lamps"[^>]*>Lamps</a>#', $html);
        $this->assertSame(2, substr_count($html, '<ul'));
        // The child list sits inside the parent item, before the parent item closes.
        $this->assertLessThan(strpos($html, 'Lamps'), strpos($html, 'Catalog'));
        $this->assertMatchesRegularExpression('#Catalog</a>\s*<ul[^>]*>.*Lamps.*</ul>\s*</li>#s', $html);
    }

    public function test_a_hidden_item_takes_its_branch_with_it(): void
    {
        $off = $this->item('Disabled', extra: ['is_active' => false]);
        $this->item('Under disabled', $off);
        $this->item('Soon', extra: ['published_at' => now()->addDay()]);
        $this->item('Shown');

        $html = $this->render();

        $this->assertStringContainsString('Shown', $html);
        $this->assertStringNotContainsString('Disabled', $html);
        $this->assertStringNotContainsString('Under disabled', $html);
        $this->assertStringNotContainsString('Soon', $html);
    }

    public function test_an_external_link_is_marked_and_a_new_tab_gets_rel(): void
    {
        $this->item('Docs', url: 'https://example.com/docs');
        $this->item('Blank', url: '/blank', extra: ['target' => '_blank']);
        $this->item('Local', url: '/local');

        $html = $this->render();

        $this->assertMatchesRegularExpression('#<a [^>]*href="https://example.com/docs" rel="noopener noreferrer">Docs</a>#', $html);
        $base = preg_quote(url('/'), '#');
        $this->assertMatchesRegularExpression('#<a [^>]*href="'.$base.'/blank" target="_blank" rel="noopener noreferrer">Blank</a>#', $html);
        $this->assertMatchesRegularExpression('#<a [^>]*href="'.$base.'/local">Local</a>#', $html);
    }

    public function test_an_item_without_an_address_is_a_plain_heading(): void
    {
        $this->item('Group', url: null);

        $this->assertMatchesRegularExpression('#<span [^>]*>Group</span>#', $this->render());
    }

    public function test_titles_are_escaped_and_a_script_address_is_not_printed(): void
    {
        $this->item('<script>alert(1)</script>', url: '/safe');
        $bad = $this->item('Bad', url: '/placeholder');
        DB::table('menu_items')->where('id', $bad->id)->update(['url' => 'javascript:alert(1)']);

        $html = $this->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertMatchesRegularExpression('#<span [^>]*>Bad</span>#', $html);
    }

    public function test_a_menu_that_does_not_exist_or_is_empty_renders_nothing(): void
    {
        $this->assertSame('', $this->render('missing'));
        $this->assertSame('', $this->render('main'));

        $this->item('Off', extra: ['is_active' => false]);

        $this->assertSame('', $this->render('main'));
    }

    public function test_a_menu_with_its_own_template_does_not_change_the_others(): void
    {
        $footer = Menu::factory()->create(['key' => 'footer']);
        $this->item('Main item');
        $this->item('Footer item', menu: $footer);
        $this->makeTheme('default', [
            'menus/menu' => 'GENERIC:{{ $items[0]["title"] }}',
            'menus/menu--footer' => 'FOOTER:{{ $items[0]["title"] }}',
        ]);
        $this->useTheme('default');

        $this->assertSame('GENERIC:Main item', $this->render('main'));
        $this->assertSame('FOOTER:Footer item', $this->render('footer'));
    }

    public function test_the_menu_hook_can_change_the_variables(): void
    {
        $this->item('One');
        $this->makeTheme('default', ['menus/menu' => '{{ $tag }}'], <<<'PHP'
<?php
use Webtack\Ecms\Theme\ThemeHooks;
return function (ThemeHooks $hooks): void {
    $hooks->preprocess('menu', function (array &$vars) { $vars['tag'] = 'HOOKED-'.$vars['key']; });
};
PHP);
        $this->useTheme('default');

        $this->assertSame('HOOKED-main', $this->render());
    }

    public function test_the_directive_places_a_menu_and_a_missing_one_does_not_break_the_template(): void
    {
        $this->item('One');
        $this->makeTheme('default', ['menus/menu' => 'M', 'probe' => '[@menu("main")][@menu("missing")]']);
        $manager = $this->useTheme('default');

        $this->assertSame('[M][]', $manager->render(['probe']));
    }

    public function test_a_relative_address_reaches_the_template_absolute_and_internal(): void
    {
        $this->item('About', url: '/about');
        $this->item('Docs', url: 'https://example.org/x');
        $this->makeTheme('default', ['menus/menu' => '@foreach($items as $i){{ $i["url"] }}|{{ var_export($i["is_external"], true) }};@endforeach']);
        $this->useTheme('default');

        $this->assertSame(url('/about').'|false;https://example.org/x|true;', $this->render());
    }

    public function test_the_active_and_trail_flags_reach_the_menu_template(): void
    {
        $parent = $this->item('Catalog', url: '/catalog');
        $this->item('Jackets', $parent, '/catalog/jackets');
        $this->makeTheme('default', ['menus/menu' => '@foreach($items as $i){{ $i["title"] }}:{{ (int) $i["is_active"] }}{{ (int) $i["is_trail"] }};@foreach($i["children"] as $c){{ $c["title"] }}:{{ (int) $c["is_active"] }}{{ (int) $c["is_trail"] }};@endforeach @endforeach']);
        $this->useTheme('default');

        $this->app->instance('request', Request::create('/catalog/jackets'));

        $this->assertStringContainsString('Catalog:01;', $this->render());
        $this->assertStringContainsString('Jackets:10;', $this->render());
    }
}
