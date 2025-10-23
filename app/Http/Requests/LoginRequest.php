<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * @bodyParam username string required The username of the user. Example: jdoe
     * @bodyParam password string required The user's password. Example: secret
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Vérifie si l'utilisateur est bloqué avant même la validation.
     */
    protected function prepareForValidation(): void
    {
        $this->ensureIsNotRateLimited();
    }

    /**
     * Incrémente le compteur en cas d'échec.
     */
    public function failedLogin(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    /**
     * Réinitialise le compteur en cas de succès.
     */
    public function successfulLogin(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
        }

    public function throttleKey(): string
    {
        return Str::lower($this->input('username')).'|'.$this->ip();
    }
}
