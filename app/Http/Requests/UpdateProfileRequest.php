<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // utilisateur connecté peut updater son profil
    }

    public function rules(): array
    {
        return [
            'name'     => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $this->user()->id,
        ];
    }
}
