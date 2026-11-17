<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\AdminMenuEntry;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * No route of the admin can be forgotten: every `admin.*` route outside the allow-list answers 403 to a role
 * without any permission (with real records behind the route parameters, so binding does not hide the check).
 */
class RouteCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes open to every active user by design (see docs/access-control.md and docs/admin-profile.md): `admin.` is the
     * bare redirect of `/admin` to the dashboard; the profile routes act only on the signed-in user.
     */
    private const ALLOWED = [
        'admin.',
        'admin.dashboard',
        'admin.profile',
        'admin.profile.update',
        'admin.profile.avatar',
        'admin.profile.password',
        'admin.lookup.content-types',
        'admin.lookup.nodes',
    ];

    /**
     * Route parameters that are not bound to a record: `{lang}` is checked inside the controller after `can:*`, and
     * `{typeKey}` is checked by the controller too, so a placeholder is enough for the access check to run first.
     */
    private const UNBOUND = ['lang', 'typeKey'];

    /** @var array<string, string> */
    private array $params = [];

    public function test_every_admin_route_outside_the_allow_list_is_forbidden_to_a_role_without_rights(): void
    {
        $user = User::factory()->withRole(Role::factory()->create())->create();
        $author = User::factory()->create();
        $this->actingAs($user);

        $checked = 0;

        foreach (Router::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'admin.') || in_array($name, self::ALLOWED, true)) {
                continue;
            }

            $uri = $this->uri($route, $author);
            $method = collect($route->methods())->first(fn (string $m) => $m !== 'HEAD');

            $this->assertSame(403, $this->call($method, $uri)->status(), "{$method} {$uri} ({$name}) is not protected");
            $checked++;
        }

        $this->assertGreaterThan(80, $checked);
    }

    public function test_the_allow_list_is_open_to_a_role_without_rights(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->actingAs(User::factory()->withRole(Role::factory()->create())->create());

        $this->get('/admin/dashboard')->assertOk();
        $this->get('/admin/profile')->assertOk();
        $this->getJson('/admin/lookup/content-types?kind=content')->assertOk();
        $this->getJson('/admin/lookup/nodes?content_type=news')->assertOk();
        $this->put('/admin/profile', ['name' => 'Same', 'email' => 'same@example.test'])->assertRedirect();
        $this->put('/admin/profile/avatar', ['avatar' => null])->assertRedirect();
        $this->put('/admin/profile/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasErrors('current_password');
    }

    /**
     * Fills the route's URI with the key/uuid of a real record per parameter. A parameter with no record (and not in
     * {@see self::UNBOUND}) fails the test, so a new route parameter cannot silently turn into a 404 placeholder.
     */
    private function uri(Route $route, User $author): string
    {
        $kind = $route->defaults['kind'] ?? 'content';
        $ids = $this->records($kind, $author);

        return preg_replace_callback('/\{(\w+)\??}/', function (array $m) use ($ids, $route) {
            if (isset($ids[$m[1]])) {
                return $ids[$m[1]];
            }

            $this->assertContains($m[1], self::UNBOUND, "Route [{$route->getName()}] has the parameter [{$m[1]}] with no record in RouteCoverageTest::records()");

            return 'x';
        }, '/'.$route->uri());
    }

    /**
     * @return array<string, string>
     */
    private function records(string $kind, User $author): array
    {
        if (isset($this->params[$kind])) {
            return $this->params[$kind];
        }

        $type = ContentType::create(['key' => "t_{$kind}", 'name' => 'T', 'kind' => $kind]);
        $field = ContentField::factory()->create(['content_type' => $type->key]);
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'T'], authorId: $author->id);
        $vocabulary = Vocabulary::first() ?? Vocabulary::factory()->create();
        $menu = Menu::first() ?? Menu::factory()->create();

        return $this->params[$kind] = [
            'contentType' => $type->key,
            'field' => $field->key,
            'node' => $node->uuid,
            'vocabulary' => $vocabulary->key,
            'term' => (Term::first() ?? Term::factory()->in($vocabulary)->create())->uuid,
            'menu' => $menu->key,
            'entry' => (string) (AdminMenuEntry::first() ?? AdminMenuEntry::create(['title' => ['default' => 'G']]))->id,
            'item' => (MenuItem::first() ?? MenuItem::factory()->in($menu)->create())->uuid,
            'language' => (AppLanguage::first() ?? AppLanguage::factory()->create())->code,
            'file' => (File::first() ?? File::factory()->create())->uuid,
            'role' => (string) Role::query()->where('is_system', false)->value('id'),
            'user' => (string) User::query()->where('id', '!=', $author->id)->value('id'),
        ];
    }
}
