<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAircraftRequest extends FormRequest
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
        $aircraftId = request()->route('aircraft')->id;
        return [
            'immatriculation' => "sometimes|string|unique:aircrafts,immatriculation,{$aircraftId}",
            'pmad' => 'nullable|integer',
            'in_activity' => 'boolean',
            'aircraft_type_id' => 'sometimes|exists:aircraft_types,id',
            'operator_id' => 'sometimes|exists:operators,id',
        ];
    }
}
