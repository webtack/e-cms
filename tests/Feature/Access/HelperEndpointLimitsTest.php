<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/** The resource-heavy helper endpoints are limited per user; the tests lower the limits through `config()`. */
class HelperEndpointLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->image('a.png', 20, 20);
    }

    public function test_upload_over_the_limit_is_429_and_stores_nothing(): void
    {
        config(['limits.upload_per_minute' => 2]);
        $this->actingAs($this->userWith(['media' => 'own']));

        $this->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertCreated();
        $this->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertCreated();
        $this->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertStatus(429);

        $this->assertSame(2, File::count());
    }

    public function test_upload_limit_of_one_user_does_not_affect_another(): void
    {
        config(['limits.upload_per_minute' => 1]);

        $this->actingAs($this->userWith(['media' => 'own']))->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertCreated();
        $this->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertStatus(429);

        $this->actingAs($this->userWith(['media' => 'own']))->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertCreated();
    }

    public function test_a_user_without_rights_still_gets_403_within_the_limit(): void
    {
        config(['limits.upload_per_minute' => 5]);

        $this->actingAs($this->userWith(['menus' => 'allow']))
            ->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertForbidden();
        $this->assertSame(0, File::count());
    }

    public function test_both_lookups_share_a_limit_per_user(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        config(['limits.lookup_per_minute' => 2]);
        $this->actingAs($this->userWith([]));

        $this->getJson('/admin/lookup/content-types?kind=content&q=')->assertOk();
        $this->getJson('/admin/lookup/nodes?content_type=news&q=')->assertOk();
        $this->getJson('/admin/lookup/nodes?content_type=news&q=')->assertStatus(429);
        $this->getJson('/admin/lookup/content-types?kind=content&q=')->assertStatus(429);

        $this->actingAs($this->userWith([]))->getJson('/admin/lookup/nodes?content_type=news&q=')->assertOk();
    }

    public function test_youtube_lookup_has_its_own_lower_limit_and_makes_no_call_over_it(): void
    {
        config(['limits.youtube_per_minute' => 1, 'limits.lookup_per_minute' => 100]);
        $this->actingAs($this->userWith(['menus' => 'allow']));
        $body = ['url' => 'https://youtu.be/dQw4w9WgXcQ'];

        $this->postJson('/admin/youtube/lookup', $body)->assertForbidden();
        $this->postJson('/admin/youtube/lookup', $body)->assertStatus(429);
        $this->getJson('/admin/lookup/content-types?kind=content&q=')->assertOk();
    }

    public function test_the_defaults_make_youtube_the_tightest_helper_limit(): void
    {
        $this->assertLessThan(config('limits.lookup_per_minute'), config('limits.youtube_per_minute'));
        $this->assertLessThan(config('limits.upload_per_minute'), config('limits.youtube_per_minute'));
    }
}
