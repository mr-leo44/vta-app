<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAircraftRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'immatriculation' => 'required|string|unique:aircrafts',
            'pmad' => 'nullable|integer',
            'in_activity' => 'boolean',
            'aircraft_type_id' => 'required|exists:aircraft_types,id',
            'operator_id' => 'required|exists:operators,id',
        ];
    }

    public function messages(): array
    {
        return [
            // 🔹 immatriculation
            'immatriculation.required' => 'L\'immatriculation de l’aéronef est requis.',
            'immatriculation.string' => 'L\'immatriculation doit être une chaîne de caractères.',
            'immatriculation.unique' => 'Cette immatriculation est déjà utilisé par un autre aéronef.',

            // 🔹 immatriculation
            'pmad.required' => 'Le pmad de l’aéronef est requis.',
            'pmad.integer' => 'Le pmad doit être un nombre entier.',
        ];
    }
}
