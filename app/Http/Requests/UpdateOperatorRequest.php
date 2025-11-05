<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $operatorId = request()->route('operator')->id;

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('operators', 'name')->ignore($operatorId)
            ],
            'sigle' => [
                'sometimes', 'required', 'string', 'max:10',
                Rule::unique('operators', 'sigle')->ignore($operatorId)
            ],
            'iata_code' => [
                'nullable', 'string', 'max:5',
                Rule::unique('operators', 'iata_code')->ignore($operatorId)
            ],
            'icao_code' => [
                'nullable', 'string', 'max:5',
                Rule::unique('operators', 'icao_code')->ignore($operatorId)
            ],
            'country' => ['nullable', 'string', 'max:100'],
            'flight_type' => ['sometimes', 'required', Rule::in(['regular', 'non_regular'])],
            'flight_nature' => ['sometimes', 'required', Rule::in(['commercial', 'non_commercial'])],
        ];
    }

    public function messages(): array
    {
        return [
            // 🔹 name
            'name.required' => 'Le nom de l’opérateur est requis.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'name.unique' => 'Ce nom est déjà utilisé par un autre opérateur.',

            // 🔹 sigle
            'sigle.required' => 'Le sigle est requis.',
            'sigle.string' => 'Le sigle doit être une chaîne de caractères.',
            'sigle.max' => 'Le sigle ne peut pas dépasser 10 caractères.',
            'sigle.unique' => 'Ce sigle est déjà utilisé.',

            // 🔹 iata_code
            'iata_code.string' => 'Le code IATA doit être une chaîne de caractères.',
            'iata_code.max' => 'Le code IATA ne peut pas dépasser 5 caractères.',
            'iata_code.unique' => 'Ce code IATA est déjà utilisé.',

            // 🔹 icao_code
            'icao_code.string' => 'Le code OACI doit être une chaîne de caractères.',
            'icao_code.max' => 'Le code OACI ne peut pas dépasser 5 caractères.',
            'icao_code.unique' => 'Ce code OACI est déjà utilisé.',

            // 🔹 country
            'country.string' => 'Le pays doit être une chaîne de caractères.',
            'country.max' => 'Le nom du pays ne peut pas dépasser 100 caractères.',

            // 🔹 flight_type
            'flight_type.required' => 'Le type de vol est requis.',
            'flight_type.in' => 'Le type de vol doit être “regular” ou “non_regular”.',

            // 🔹 flight_nature
            'flight_nature.required' => 'La nature du vol est requise.',
            'flight_nature.in' => 'La nature du vol doit être “commercial” ou “non_commercial”.',
        ];
    }
}
