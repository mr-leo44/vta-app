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
        $aircraftId = request()->route('aircraft')?->id;
        return [
            'immatriculation' => 'sometimes|required|string|unique:aircrafts,immatriculation,' . ($aircraftId ?? 'NULL'),
            'pmad' => 'sometimes|required|integer|min:0',
            'in_activity' => 'sometimes|boolean',
            'aircraft_type_id' => 'sometimes|required|exists:aircraft_types,id',
            'operator_id' => 'sometimes|required|exists:operators,id',
        ];
    }
}
