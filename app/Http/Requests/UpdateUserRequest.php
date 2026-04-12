<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('user.update');
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:100'],
            'username' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($this->route('user')),
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],
            'password' => ['sometimes', 'string', 'max:100', 'confirmed', Password::defaults()],
        ];
    }
}
