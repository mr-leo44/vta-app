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
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration' => 'required|string|unique:aircrafts',
            'pmad' => 'nullable|integer',
            'in_activity' => 'boolean',
            'aircraft_type_id' => 'required|exists:aircraft_types,id',
            'operator_id' => 'required|exists:operators,id',
        ];
    }
}
