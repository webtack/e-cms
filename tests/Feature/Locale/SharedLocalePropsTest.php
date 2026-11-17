<?php

namespace Webtack\Ecms\Tests\Feature\Locale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Tests\TestCase;

class SharedLocalePropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_prop_has_current_available_and_messages(): void
    {
        $this->get('/login', ['Accept-Language' => 'uk'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.current', 'uk')
                ->where('locale.available', [
                    ['code' => 'en', 'name' => 'English'],
                    ['code' => 'uk', 'name' => 'Українська'],
                ])
                ->where('locale.messages.Log in', 'Увійти'));
    }

    public function test_english_messages_are_delivered_as_is(): void
    {
        $this->get('/login')
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.current', 'en')
                ->where('locale.messages.Log in', 'Log in'));
    }
}
