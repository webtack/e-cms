<?php

namespace Webtack\Ecms\Tests\Feature\Locale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ServerMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_password_error_is_translated_for_a_ukrainian_visitor(): void
    {
        $user = User::factory()->create();

        $this->withCookie('locale', 'uk')
            ->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Ці облікові дані не збігаються з нашими записами.']);
    }

    public function test_wrong_password_error_is_english_by_default(): void
    {
        config(['app.locale' => 'en']);
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_validation_message_uses_the_translated_attribute_name(): void
    {
        $this->withCookie('locale', 'uk')
            ->from('/login')
            ->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => 'Поле електронна пошта обов’язкове.',
                'password' => 'Поле пароль обов’язкове.',
            ]);
    }
}
