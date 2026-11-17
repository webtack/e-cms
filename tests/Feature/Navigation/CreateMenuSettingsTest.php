<?php

namespace Webtack\Ecms\Tests\Feature\Navigation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class CreateMenuSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/settings/navigation/create-types';

    private const KEY = 'dashboard.create_types';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['a', 'b', 'c'] as $key) {
            ContentType::create(['key' => $key, 'name' => strtoupper($key)]);
        }

        $this->actingAs(User::factory()->create());
    }

    private function stored(): mixed
    {
        return app(AppSettings::class)->get(self::KEY);
    }

    public function test_the_list_is_saved_in_the_given_order(): void
    {
        $this->put(self::URL, ['types' => ['c', 'a']])->assertRedirect();
        $this->assertSame(['c', 'a'], $this->stored());

        $this->put(self::URL, ['types' => ['a', 'c', 'b']])->assertRedirect();
        $this->assertSame(['a', 'c', 'b'], $this->stored());
    }

    public function test_an_empty_list_clears_the_setting(): void
    {
        app(AppSettings::class)->set(self::KEY, ['a']);

        $this->put(self::URL, ['types' => []])->assertRedirect();

        $this->assertFalse(app(AppSettings::class)->has(self::KEY));
    }

    public function test_an_unknown_or_repeated_type_is_rejected(): void
    {
        app(AppSettings::class)->set(self::KEY, ['a']);

        $this->put(self::URL, ['types' => ['a', 'nope']])->assertSessionHasErrors('types.1');
        $this->put(self::URL, ['types' => ['a', 'a']])->assertSessionHasErrors(['types.0', 'types.1']);

        $this->assertSame(['a'], $this->stored());
    }

    public function test_the_list_is_required(): void
    {
        $this->put(self::URL, [])->assertSessionHasErrors('types');
    }

    public function test_the_settings_permission_is_required(): void
    {
        $this->actingAs(User::factory()->withRole(Role::factory()->withPermissions(['menus' => 'allow'])->create())->create());

        $this->put(self::URL, ['types' => ['a']])->assertForbidden();

        $this->assertNull($this->stored());
    }

    public function test_the_page_props_carry_the_saved_list_and_the_free_types(): void
    {
        app(AppSettings::class)->set(self::KEY, ['c', 'gone', 'a']);

        $this->get('/admin/settings/navigation')->assertInertia(fn (Assert $page) => $page
            ->where('createTypes', [
                ['key' => 'c', 'name' => 'C', 'kind' => 'content'],
                ['key' => 'a', 'name' => 'A', 'kind' => 'content'],
            ])
            ->where('createAvailableTypes', [['key' => 'b', 'name' => 'B', 'kind' => 'content']]));
    }

    public function test_the_props_are_empty_when_not_configured(): void
    {
        $this->get('/admin/settings/navigation')->assertInertia(fn (Assert $page) => $page
            ->where('createTypes', [])
            ->has('createAvailableTypes', 3));
    }
}
