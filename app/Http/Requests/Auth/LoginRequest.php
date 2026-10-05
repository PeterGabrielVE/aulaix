<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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

        $credentials = $this->only('email', 'password');

        if (! Auth::attemptWhen($credentials, fn (User $user) => $user->isActive(), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans($this->isDeactivatedAccount($credentials) ? 'auth.inactive' : 'auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Whether the failed attempt had the right password for an account that
     * has been deactivated — only then is it safe to say so instead of the
     * generic "wrong credentials" message.
     */
    private function isDeactivatedAccount(array $credentials): bool
    {
        $guard = Auth::guard('web');
        $user = $guard->getLastAttempted();

        return $user instanceof User
            && ! $user->isActive()
            && $guard->getProvider()->validateCredentials($user, $credentials);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        // Scoped per institution: the same email is a different account in
        // each one, so failed attempts in one must not lock out the other.
        return Str::transliterate(
            app(CurrentTenant::class)->id().'|'.Str::lower($this->string('email')).'|'.$this->ip()
        );
    }
}
