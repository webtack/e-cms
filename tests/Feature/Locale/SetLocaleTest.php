<?php

namespace Webtack\Ecms\Tests\Feature\Locale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_used_without_any_hint(): void
    {
        config(['app.locale' => 'en']);

        $this->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'en'));
    }

    public function test_accept_language_with_region_matches_the_base_locale(): void
    {
        $this->get('/login', ['Accept-Language' => 'uk-UA,uk;q=0.9'])
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_unsupported_accept_language_falls_back_to_default(): void
    {
        config(['app.locale' => 'uk']);

        $this->get('/login', ['Accept-Language' => 'de'])
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_missing_accept_language_falls_back_to_default(): void
    {
        config(['app.locale' => 'uk']);

        // Symfony's test request adds `Accept-Language: en-us` unless it is blanked.
        $this->get('/login', ['Accept-Language' => ''])
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_accept_language_skips_unsupported_entries(): void
    {
        $this->get('/login', ['Accept-Language' => 'de,uk;q=0.8'])
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_cookie_wins_over_accept_language(): void
    {
        $this->withCookie('locale', 'uk')
            ->get('/login', ['Accept-Language' => 'en'])
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_invalid_cookie_is_ignored(): void
    {
        $this->withCookie('locale', 'xx')
            ->get('/login', ['Accept-Language' => 'uk'])
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_user_locale_wins_over_cookie_and_accept_language(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'uk'])->save();

        $this->actingAs($user)
            ->withCookie('locale', 'en')
            ->get('/admin/dashboard', ['Accept-Language' => 'en'])
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_user_without_saved_locale_falls_through_to_cookie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withCookie('locale', 'uk')
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'uk'));
    }

    public function test_html_lang_attribute_matches_the_current_locale(): void
    {
        $this->get('/login', ['Accept-Language' => 'uk'])
            ->assertSee('lang="uk"', false);
    }

    public function test_public_site_ignores_user_cookie_and_accept_language(): void
    {
        config(['app.locale' => 'uk']);

        $user = User::factory()->create();
        $user->forceFill(['locale' => 'en'])->save();

        $this->actingAs($user)
            ->withCookie('locale', 'en')
            ->get('/', ['Accept-Language' => 'en'])
            ->assertSee('lang="uk"', false);
    }
}
