<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->user = User::factory()->withRole(Role::factory()->create(['name' => 'Editor']))->create();
    }

    /** A stored file of the given upload profile. */
    private function file(string $profile = 'avatar', string $ext = 'jpg'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create([
            'uuid' => $uuid, 'profile' => $profile, 'path' => "{$uuid}.{$ext}", 'mime' => "image/{$ext}", 'user_id' => $this->user->id,
        ]);
    }

    /** @return list<string> uuids of the files related to the user's avatar */
    private function related(): array
    {
        return DB::table('media_relations')
            ->where('owner_type', 'user')->where('owner_id', $this->user->id)->where('role', 'avatar')
            ->pluck('file_uuid')->all();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->put('/admin/profile/avatar', ['avatar' => ''])->assertRedirect('/login');
    }

    public function test_an_avatar_is_set_and_bound_in_the_media_library(): void
    {
        $file = $this->file();

        $this->actingAs($this->user)->put('/admin/profile/avatar', ['avatar' => $file->uuid])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame("{$file->uuid}.jpg", $this->user->fresh()->avatar);
        $this->assertSame([$file->uuid], $this->related());
    }

    public function test_an_avatar_is_replaced_and_the_previous_file_is_released(): void
    {
        $first = $this->file();
        $second = $this->file(ext: 'png');
        $this->actingAs($this->user)->put('/admin/profile/avatar', ['avatar' => $first->uuid]);

        $this->put('/admin/profile/avatar', ['avatar' => $second->uuid])->assertRedirect();

        $this->assertSame("{$second->uuid}.png", $this->user->fresh()->avatar);
        $this->assertSame([$second->uuid], $this->related());
    }

    public function test_an_avatar_is_removed_and_the_file_is_released(): void
    {
        $file = $this->file();
        $this->actingAs($this->user)->put('/admin/profile/avatar', ['avatar' => $file->uuid]);

        $this->put('/admin/profile/avatar', ['avatar' => ''])->assertRedirect()->assertSessionHas('success');

        $this->assertNull($this->user->fresh()->avatar);
        $this->assertSame([], $this->related());
        $this->assertNotNull(File::findByUuid($file->uuid));
    }

    public function test_a_file_of_another_profile_is_rejected_and_nothing_changes(): void
    {
        $current = $this->file();
        $this->actingAs($this->user)->put('/admin/profile/avatar', ['avatar' => $current->uuid]);
        $document = $this->file('document', 'pdf');

        $this->from('/admin/profile')->put('/admin/profile/avatar', ['avatar' => $document->uuid])
            ->assertRedirect('/admin/profile')->assertSessionHasErrors('avatar');

        $this->assertSame("{$current->uuid}.jpg", $this->user->fresh()->avatar);
        $this->assertSame([$current->uuid], $this->related());
    }

    public function test_an_unknown_file_is_rejected(): void
    {
        $this->actingAs($this->user)->put('/admin/profile/avatar', ['avatar' => str_repeat('a', 32)])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($this->user->fresh()->avatar);
    }

    public function test_the_shared_prop_carries_the_avatar_url_or_null(): void
    {
        $this->actingAs($this->user)->get('/admin/profile')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar', null));

        $file = $this->file();
        $this->put('/admin/profile/avatar', ['avatar' => $file->uuid]);

        $this->get('/admin/profile')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar', Storage::disk('public')->url("{$file->uuid}.jpg")));
    }

    public function test_the_avatar_is_not_mass_assignable_from_the_personal_data_form(): void
    {
        $this->actingAs($this->user)->put('/admin/profile', ['name' => 'Anna', 'email' => $this->user->email, 'avatar' => 'evil.jpg']);

        $this->assertNull($this->user->fresh()->avatar);
    }
}
