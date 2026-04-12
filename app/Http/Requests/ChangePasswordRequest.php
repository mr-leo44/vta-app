<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // utilisateur connecté peut changer son mdp
    }

    public function rules(): array
    {
        return [
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed|' .Password::defaults(),
            'password_confirmation' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'password.min'       => 'Le mot de passe doit avoir au minimum 8 caractères.',
            'password.required'  => 'Le mot de passe est requis.',
            'current_password.required' => 'Le mot de passe actuel est requis.',
            'password_confirmation.required' => 'La confirmation du mot de passe est requise.',
            'password_confirmation.confirmed' => 'Les mots de passe ne correspondent pas.',
        ];
    }
}
