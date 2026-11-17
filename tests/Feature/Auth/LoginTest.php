<?php

namespace Webtack\Ecms\Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_user_can_log_in_and_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_returns_error_and_does_not_authenticate(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_from_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_many_emails_from_one_ip_are_refused_after_the_ip_limit(): void
    {
        config(['limits.login_ip_attempts' => 4]);
        $user = User::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => "nobody{$i}@example.com", 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_another_ip_logs_in_while_one_ip_is_limited(): void
    {
        config(['limits.login_ip_attempts' => 2]);
        $user = User::factory()->create();

        for ($i = 0; $i < 2; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->post('/login', ['email' => "nobody{$i}@example.com", 'password' => 'wrong']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_successful_login_does_not_reset_the_ip_counter(): void
    {
        config(['limits.login_ip_attempts' => 3]);
        $user = User::factory()->create();

        $this->post('/login', ['email' => 'nobody1@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'nobody2@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin/dashboard');
        $this->post('/logout');

        $this->post('/login', ['email' => 'nobody3@example.com', 'password' => 'wrong']);

        // Three failures in total, so the IP is now limited even for correct credentials.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_see_admin_and_gets_notice_page(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/admin/dashboard')
            ->assertRedirect(route('verification.notice'));

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/VerifyEmail'));
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_open_session_of_a_deactivated_user_ends_at_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_reactivated_user_can_log_in_again(): void
    {
        $user = User::factory()->inactive()->create();
        $user->update(['is_active' => true]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin/dashboard');
    }
}
