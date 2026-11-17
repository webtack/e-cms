<?php

namespace Webtack\Ecms\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // A deactivated user fails the same way as a wrong password, so the response does not reveal the account.
        $credentials = [...$this->only('email', 'password'), 'is_active' => true];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            // A failure counts against both the email + IP pair and the IP alone (all emails).
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey(), max(1, (int) config('limits.login_ip_window')));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Only the pair is cleared: the per-IP counter must survive a login to a valid account,
        // otherwise an attacker owning one account could reset it after every few guesses.
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        $pairLimited = RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS);
        $ipLimited = RateLimiter::tooManyAttempts($this->ipThrottleKey(), max(1, (int) config('limits.login_ip_attempts')));

        if (! $pairLimited && ! $ipLimited) {
            return;
        }

        event(new Lockout($this));

        // The wait is the longer of the exhausted counters; the message is the same for both.
        $seconds = max(
            $pairLimited ? RateLimiter::availableIn($this->throttleKey()) : 0,
            $ipLimited ? RateLimiter::availableIn($this->ipThrottleKey()) : 0,
        );

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key (email + IP).
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }

    /**
     * Get the per-IP throttle key (all emails from one address).
     */
    private function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
