<?php

namespace App\Http\Requests;

use App\Traits\RateLimited;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class LoginRequest extends FormRequest
{
    use RateLimited;
    /**
     * @bodyParam username string required The username of the user. Example: jdoe
     * @bodyParam password string required The user's password. Example: secret
     */
    public function authorize(): bool
    {
        // Ensure rate limiting is enforced before validation
        $this->ensureIsNotRateLimited();
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                Password::min(8),  // P1: Minimum 8 characters
            ],
        ];
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

    public function throttleKey(): string
    {
        return Str::lower($this->input('username')).'|'.$this->ip();
    }
}
