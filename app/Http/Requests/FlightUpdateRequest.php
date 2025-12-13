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
            'flight_number' => 'sometimes|string',
            'operator_id' => 'sometimes|exists:operators,id',
            'aircraft_id' => 'sometimes|exists:aircrafts,id',
            'departure' => 'sometimes|array',
            'arrival' => 'sometimes|array',
            'departure_time' => 'sometimes|date|after:arrival_time',
            'arrival_time' => 'sometimes|date',
            'flight_type' => 'sometimes|in:regular,non_regular',
            'flight_nature' => 'sometimes|in:commercial,state,test,humanitare,afreightment,requisition',
            'flight_regime' => 'sometimes|in:domestic,international',
            'status' => 'sometimes|in:qrf,prevu,atteri,annule,detourne',
            'remarks' => 'nullable|string',
            'statistics' => 'nullable|array',
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Flight number
            'flight_number.required' => 'Le numero de vol est requis.',
            'flight_number.string' => 'Le numero de vol doit être une chaîne de caractères.',
            'flight_number.unique' => 'Ce numero de vol est déjà utilisée par un autre vol.',

            // Departure
            'departure.required' => 'Le lieu de depart est requis',

            // Arrival
            'arrival.required' => 'Le lieu d\'arrivée est requis',

            // Departure time
            'departure_time.required' => 'L\'heure de départ est requise.',
            'departure_time.date' => 'L\'heure de départ doit être une date valide.',
            'departure_time.after' => 'L\'heure de départ doit être postérieure à l\'heure d\'arrivée.',

            // Arrival time
            'arrival_time.required' => 'L\'heure d\'arrivée est requise.',
            'arrival_time.date' => 'L\'heure d\'arrivée doit être une date valide.',

            // Flight type
            'flight_type.in' => 'Le type de vol doit être "regular" ou "non_regular".',

            // Flight nature
            'flight_nature.in' => 'La nature du vol doit être "commercial" ou "non_commercial" (d\'Etat, Test, Humanitaire, Affrètement, Requisition).',

            // Flight regime
            'flight_regime.in' => 'Le régime de vol doit être "domestic" ou "international".',

            // Status
            'status.in' => 'Le statut du vol doit être parmi: qrf, prevu, atteri, annule, detourne.',
        ];
    }
}
