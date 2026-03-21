<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterFlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'flight_regime' => ['nullable', 'string', 'max:50'],
            'flight_type' => ['nullable', 'string', 'max:50'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'aircraft_id' => ['nullable', 'integer', 'exists:aircrafts,id'],
            'departure_date_from' => ['nullable', 'date'],
            'departure_date_to' => ['nullable', 'date', 'after_or_equal:departure_date_from'],
            'sort' => ['nullable', 'in:flight_number:asc,flight_number:desc,departure_time:asc,departure_time:desc,created_at:desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'La recherche doit être une chaîne de caractères.',
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'status.string' => 'Le statut doit être une chaîne de caractères.',
            'flight_regime.string' => 'Le régime doit être une chaîne de caractères.',
            'flight_type.string' => 'Le type de vol doit être une chaîne de caractères.',
            'operator_id.exists' => 'L\'opérateur spécifié n\'existe pas.',
            'aircraft_id.exists' => 'L\'aéronef spécifié n\'existe pas.',
            'departure_date_from.date' => 'La date de départ (from) doit être une date valide.',
            'departure_date_to.date' => 'La date de départ (to) doit être une date valide.',
            'departure_date_to.after_or_equal' => 'La date de départ (to) doit être égale ou après la date (from).',
            'sort.in' => 'Le tri invalide. Options: flight_number:asc, flight_number:desc, departure_time:asc, departure_time:desc, created_at:desc.',
            'per_page.integer' => 'Le nombre par page doit être un nombre entier.',
            'per_page.min' => 'Le nombre par page doit être au minimum 1.',
            'per_page.max' => 'Le nombre par page ne peut pas dépasser 100.',
        ];
    }

    public function getFilters(): array
    {
        return [
            'search' => $this->input('search'),
            'status' => $this->input('status'),
            'flight_regime' => $this->input('flight_regime'),
            'flight_type' => $this->input('flight_type'),
            'operator_id' => $this->input('operator_id'),
            'aircraft_id' => $this->input('aircraft_id'),
            'departure_date_from' => $this->input('departure_date_from'),
            'departure_date_to' => $this->input('departure_date_to'),
            'sort' => $this->input('sort', 'departure_time:desc'),
            'per_page' => $this->input('per_page', 15),
        ];
    }
}
