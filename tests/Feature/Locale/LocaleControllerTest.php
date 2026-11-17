<?php

namespace Webtack\Ecms\Tests\Feature\Locale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class LocaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_choice_is_stored_in_a_cookie(): void
    {
        $this->from('/login')
            ->post('/locale', ['locale' => 'uk'])
            ->assertRedirect('/login')
            ->assertCookie('locale', 'uk');
    }

    public function test_authenticated_choice_is_saved_to_the_user_and_cookie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/admin/dashboard')
            ->post('/locale', ['locale' => 'uk'])
            ->assertRedirect('/admin/dashboard')
            ->assertCookie('locale', 'uk');

        $this->assertSame('uk', $user->fresh()->locale);
    }

    public function test_invalid_locale_is_rejected_and_nothing_changes(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'en'])->save();

        $this->actingAs($user)
            ->from('/admin/dashboard')
            ->post('/locale', ['locale' => 'xx'])
            ->assertSessionHasErrors('locale')
            ->assertCookieMissing('locale');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_locale_is_required(): void
    {
        $this->post('/locale', [])->assertSessionHasErrors('locale');
    }

    public function test_choice_survives_logout_through_the_cookie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/locale', ['locale' => 'uk']);
        $this->post('/logout');

        $this->withCookie('locale', 'uk')
            ->get('/login')
            ->assertSee('lang="uk"', false);
    }
}
