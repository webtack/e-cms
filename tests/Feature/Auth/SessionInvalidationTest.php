<?php

namespace Webtack\Ecms\Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'old-password']);
    }

    /**
     * Starts a fresh test "browser": no session data, no cached guard user.
     */
    private function newBrowser(): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this;
    }

    /**
     * Signs the user in through the login form and returns the session data the second browser would hold.
     *
     * @return array<string, mixed>
     */
    private function loginSession(array $extra = []): array
    {
        $this->newBrowser()->post('/login', ['email' => 'anna@example.com', 'password' => 'old-password', ...$extra])
            ->assertRedirect('/admin/dashboard');

        return $this->app['session']->all();
    }

    /**
     * Replays a session captured earlier as if it came from another browser.
     */
    private function replay(array $session, ?User $as = null): static
    {
        $this->newBrowser();
        $this->withSession($session);

        return $this->actingAs($as ?? $this->user->fresh());
    }

    public function test_a_password_change_on_the_profile_page_ends_the_other_session_and_keeps_the_current_one(): void
    {
        $this->loginSession();
        $this->get('/admin/profile')->assertOk();
        $other = $this->app['session']->all();

        $this->newBrowser()->post('/login', ['email' => 'anna@example.com', 'password' => 'old-password']);
        $this->get('/admin/profile')->assertOk();

        $this->put('/admin/profile/password', ['current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect();

        $this->get('/admin/profile')->assertOk();

        $this->replay($other)->get('/admin/profile')->assertRedirect('/login');
    }

    public function test_an_administrator_reset_ends_the_users_session_but_not_the_administrators(): void
    {
        $admin = User::factory()->create();
        $other = $this->loginSession();

        $this->newBrowser()->actingAs($admin);
        $this->get('/admin/users')->assertOk();
        $this->put("/admin/users/{$this->user->id}", [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'password' => 'brand-new-pass',
            'role_id' => $this->user->role_id,
        ])->assertRedirect();
        $this->get('/admin/users')->assertOk();

        $this->replay($other)->get('/admin/profile')->assertRedirect('/login');
    }

    public function test_a_remember_me_cookie_is_refused_after_the_password_changed(): void
    {
        $this->newBrowser()->post('/login', ['email' => 'anna@example.com', 'password' => 'old-password', 'remember' => 1]);
        $cookie = collect($this->app['cookie']->getQueuedCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'The login with remember=1 must queue a remember cookie.');

        // The cookie alone signs a new browser in until the password changes.
        $this->newBrowser()->withCookie($cookie->getName(), $cookie->getValue())->get('/admin/profile')->assertOk();

        $this->user->update(['password' => 'new-password']);

        $this->newBrowser()->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/admin/profile')->assertRedirect('/login');
    }

    public function test_changing_name_email_or_role_keeps_the_session(): void
    {
        $other = $this->loginSession();

        $this->user->update(['name' => 'Anna B.', 'email' => 'new@example.com', 'role_id' => Role::factory()->create()->id]);

        $this->replay($other)->get('/admin/profile')->assertOk();
    }

    public function test_a_session_without_the_stored_hash_is_served(): void
    {
        $this->newBrowser()->actingAs($this->user)->get('/admin/profile')->assertOk();
    }
}
