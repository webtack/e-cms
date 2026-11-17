<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SiteSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function snapshot(): array
    {
        return DB::table('app_settings')->orderBy('key')->get(['key', 'value'])->all();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
        $this->put('/admin/settings', ['name' => 'X'])->assertRedirect('/login');
    }

    public function test_a_role_without_the_permission_gets_403(): void
    {
        $role = Role::factory()->withPermissions(['users' => 'allow'])->create();
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings', ['name' => 'X'])->assertForbidden();
        $this->assertSame([], $this->snapshot());
    }

    public function test_a_role_with_the_permission_opens_the_page(): void
    {
        $role = Role::factory()->withPermissions(['settings' => 'allow'])->create();
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get('/admin/settings')->assertOk();
    }

    public function test_the_page_of_an_empty_installation(): void
    {
        $this->admin()->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Site')
                ->where('values.name', '')
                ->where('values.description', '')
                ->where('values.address', '')
                ->where('values.emails', [])
                ->where('values.schedule', [])
                ->where('values.indexable', false)
                ->where('logoFiles.logo_large', null)
                ->has('networkOptions', count(config('social_networks')))
                ->where('networkOptions.0', ['value' => 'facebook', 'label' => 'Facebook'])
                ->where('limits.list', 20));
    }

    public function test_a_stored_logo_carries_its_thumb_preview(): void
    {
        $uuid = File::newUuid();
        File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
        app(SiteSettings::class)->save(['logo_large' => "{$uuid}.png"]);

        $this->admin()->get('/admin/settings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('logoFiles.logo_large.url', Storage::disk('public')->url("{$uuid}.png"))
                ->where('logoFiles.logo_large.preview', Storage::disk('public')->url("styles/thumb/{$uuid}.webp")));
    }

    public function test_the_page_shows_the_saved_values(): void
    {
        app(SiteSettings::class)->save([
            'name' => 'My site',
            'phones' => [['value' => '+380 1', 'label' => 'Sales'], ['value' => '+380 2', 'label' => '']],
            'schedule' => [['Mon', '9', '18', '']],
            'indexable' => true,
        ]);

        $this->admin()->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
            ->where('values.name', 'My site')
            ->where('values.phones.0', ['value' => '+380 1', 'label' => 'Sales'])
            ->where('values.phones.1.value', '+380 2')
            ->where('values.schedule', [['Mon', '9', '18', '']])
            ->where('values.indexable', true));
    }

    public function test_an_unknown_stored_network_stays_selectable(): void
    {
        app(AppSettings::class)->set('site.socials', [['network' => 'myspace', 'url' => 'https://m.example']]);

        $this->admin()->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
            ->has('networkOptions', count(config('social_networks')) + 1)
            ->where('networkOptions.'.count(config('social_networks')), ['value' => 'myspace', 'label' => 'myspace']));
    }

    public function test_saving_stores_the_values_and_redirects_back(): void
    {
        $this->admin()->from('/admin/settings')->put('/admin/settings', [
            'name' => 'My site',
            'emails' => [['value' => 'a@b.co', 'label' => 'General']],
            'indexable' => true,
        ])->assertRedirect('/admin/settings')->assertSessionHas('success');

        $site = app(SiteSettings::class);
        $this->assertSame('My site', $site->name());
        $this->assertSame([['value' => 'a@b.co', 'label' => 'General']], $site->emails());
        $this->assertTrue($site->indexable());
    }

    public function test_saving_unchanged_values_twice_changes_nothing(): void
    {
        $data = ['name' => 'A', 'phones' => [['value' => '+380 1']], 'schedule' => [['Mon', '', '', '']], 'indexable' => false];

        $this->admin()->put('/admin/settings', $data)->assertSessionDoesntHaveErrors();
        $first = $this->snapshot();
        $this->put('/admin/settings', $data);

        $this->assertEquals($first, $this->snapshot());
    }

    public function test_a_validation_error_changes_nothing(): void
    {
        app(SiteSettings::class)->save(['name' => 'Before']);
        $before = $this->snapshot();

        $this->admin()->from('/admin/settings')->put('/admin/settings', ['name' => 'After', 'emails' => [['value' => 'nope']]])
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('emails.0.value');

        $this->assertEquals($before, $this->snapshot());
    }
}
