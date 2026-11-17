<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['name' => 'Editor']);
        $this->user = User::factory()->withRole($role)->create([
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'old-password',
        ]);
    }

    private function passwordPayload(array $over = []): array
    {
        return ['current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password', ...$over];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/profile')->assertRedirect('/login');
        $this->put('/admin/profile', ['name' => 'X', 'email' => 'x@example.com'])->assertRedirect('/login');
        $this->put('/admin/profile/password', $this->passwordPayload())->assertRedirect('/login');
    }

    public function test_a_user_without_any_right_sees_their_own_profile(): void
    {
        $this->actingAs($this->user)->get('/admin/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Profile')
                ->where('profile', ['name' => 'Anna', 'email' => 'anna@example.com', 'role' => 'Editor', 'avatar' => null]));
    }

    public function test_name_and_email_are_saved(): void
    {
        $this->actingAs($this->user)
            ->from('/admin/profile')
            ->put('/admin/profile', ['name' => 'Anna B.', 'email' => 'new@example.com'])
            ->assertRedirect('/admin/profile')
            ->assertSessionHas('success');

        $this->user->refresh();
        $this->assertSame('Anna B.', $this->user->name);
        $this->assertSame('new@example.com', $this->user->email);
    }

    public function test_own_email_can_be_resubmitted(): void
    {
        $this->actingAs($this->user)
            ->put('/admin/profile', ['name' => 'Anna B.', 'email' => 'anna@example.com'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Anna B.', $this->user->refresh()->name);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->user)
            ->put('/admin/profile', ['name' => 'Anna', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame('anna@example.com', $this->user->refresh()->email);
    }

    public function test_name_and_email_are_required(): void
    {
        $this->actingAs($this->user)
            ->put('/admin/profile', ['name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_role_and_activity_are_ignored(): void
    {
        $other = Role::factory()->create();

        $this->actingAs($this->user)
            ->put('/admin/profile', ['name' => 'Anna', 'email' => 'anna@example.com', 'role_id' => $other->id, 'is_active' => 0]);

        $this->user->refresh();
        $this->assertNotSame($other->id, $this->user->role_id);
        $this->assertTrue($this->user->is_active);
    }

    public function test_email_verification_is_kept_when_the_email_changes(): void
    {
        $verifiedAt = $this->user->email_verified_at;
        $this->assertNotNull($verifiedAt);

        $this->actingAs($this->user)->put('/admin/profile', ['name' => 'Anna', 'email' => 'new@example.com']);

        $this->assertEquals($verifiedAt, $this->user->refresh()->email_verified_at);
        $this->get('/admin/profile')->assertOk();
    }

    public function test_password_is_changed(): void
    {
        $this->actingAs($this->user)
            ->from('/admin/profile')
            ->put('/admin/profile/password', $this->passwordPayload())
            ->assertRedirect('/admin/profile')
            ->assertSessionHas('success');

        $hash = $this->user->refresh()->password;
        $this->assertTrue(Hash::check('new-password', $hash));
        $this->assertFalse(Hash::check('old-password', $hash));
        // The session that changed its own password stays signed in.
        $this->get('/admin/profile')->assertOk();
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->put('/admin/profile/password', $this->passwordPayload(['current_password' => 'wrong-password']))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $this->user->refresh()->password));
    }

    public function test_short_or_unconfirmed_password_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->put('/admin/profile/password', $this->passwordPayload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');

        $this->actingAs($this->user)
            ->put('/admin/profile/password', $this->passwordPayload(['password_confirmation' => 'different-one']))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $this->user->refresh()->password));
    }
}
