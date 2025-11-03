<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlightUpdateRequest extends FormRequest
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
            'flight_number' => 'sometimes|required|string',
            'operator_id' => 'sometimes|required|exists:operators,id',
            'aircraft_id' => 'sometimes|required|exists:aircrafts,id',
            'departure' => 'sometimes|required|array',
            'arrival' => 'sometimes|required|array',
            'departure_time' => 'sometimes|required|date',
            'arrival_time' => 'sometimes|required|date|after:departure_time',
            'remarks' => 'nullable|string',
            'statistics' => 'nullable|array',
        ];
    }
}
