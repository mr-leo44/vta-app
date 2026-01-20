<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterAircraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:50'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'aircraft_type_id' => ['nullable', 'integer', 'exists:aircraft_types,id'],
            'pmad_from' => ['nullable', 'numeric', 'min:0'],
            'pmad_to' => ['nullable', 'numeric', 'min:0', 'gte:pmad_from'],
            'in_activity' => ['nullable', 'boolean'],
            'with_flights' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:immatriculation:asc,immatriculation:desc,created_at:asc,created_at:desc,updated_at:desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'La recherche doit être une chaîne de caractères.',
            'search.max' => 'La recherche ne peut pas dépasser 50 caractères.',
            'operator_id.exists' => 'L\'opérateur spécifié n\'existe pas.',
            'aircraft_type_id.exists' => 'Le type d\'aéronef spécifié n\'existe pas.',
            'pmad_from.numeric' => 'Le PMAD (from) doit être un nombre.',
            'pmad_from.min' => 'Le PMAD (from) doit être au minimum 0.',
            'pmad_to.numeric' => 'Le PMAD (to) doit être un nombre.',
            'pmad_to.min' => 'Le PMAD (to) doit être au minimum 0.',
            'pmad_to.gte' => 'Le PMAD (to) doit être égal ou supérieur au PMAD (from).',
            'in_activity.boolean' => 'in_activity doit être booléen.',
            'with_flights.boolean' => 'with_flights doit être booléen.',
            'sort.in' => 'Le tri invalide. Options: immatriculation:asc, immatriculation:desc, created_at:asc, created_at:desc, updated_at:desc.',
            'per_page.integer' => 'Le nombre par page doit être un nombre entier.',
            'per_page.min' => 'Le nombre par page doit être au minimum 1.',
            'per_page.max' => 'Le nombre par page ne peut pas dépasser 100.',
        ];
    }

    public function getFilters(): array
    {
        return [
            'search' => $this->input('search'),
            'operator_id' => $this->input('operator_id'),
            'aircraft_type_id' => $this->input('aircraft_type_id'),
            'pmad_from' => $this->input('pmad_from'),
            'pmad_to' => $this->input('pmad_to'),
            'in_activity' => $this->input('in_activity'),
            'with_flights' => $this->input('with_flights'),
            'sort' => $this->input('sort', 'immatriculation:asc'),
            'per_page' => $this->input('per_page', 15),
        ];
    }
}
