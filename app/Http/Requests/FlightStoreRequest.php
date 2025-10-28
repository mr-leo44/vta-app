<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlightStoreRequest extends FormRequest
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
            'flight_number' => 'required|string|unique:flights,flight_number',
            'operator_id' => 'required|exists:operators,id',
            'aircraft_id' => 'required|exists:aircrafts,id',
            'departure' => 'required|array',
            'arrival' => 'required|array',
            'departure_time' => 'required|date',
            'arrival_time' => 'required|date|after:departure_time',
            'remarks' => 'nullable|string',
            'statistics' => 'nullable|array',
        ];
    }
}
