<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'flight_type' => ['nullable', 'in:regular,non_regular'],
            'flight_regime' => ['nullable', 'in:domestic,international'],
            'flight_nature' => ['nullable', 'in:commercial,non_commercial'],
            'sort' => ['nullable', 'in:name:asc,name:desc,created_at:asc,created_at:desc,updated_at:desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Le terme de recherche doit être une chaîne de caractères.',
            'search.max' => 'Le terme de recherche ne peut pas dépasser 100 caractères.',
            
            'country.string' => 'Le pays doit être une chaîne de caractères.',
            'country.max' => 'Le pays ne peut pas dépasser 100 caractères.',
            
            'flight_type.in' => 'Le type de vol doit être "regular" ou "non_regular".',
            'flight_regime.in' => 'Le régime doit être "domestic" ou "international".',
            'flight_nature.in' => 'La nature doit être "commercial" ou "non_commercial".',
            'sort.in' => 'Le tri invalide. Options: name:asc, name:desc, created_at:asc, created_at:desc, updated_at:desc.',
            
            'per_page.integer' => 'Le nombre par page doit être un nombre entier.',
            'per_page.min' => 'Le nombre par page doit être au minimum 1.',
            'per_page.max' => 'Le nombre par page ne peut pas dépasser 100.',
        ];
    }

    /**
     * Get filters for repository.
     */
    public function getFilters(): array
    {
        return [
            'search' => $this->input('search'),
            'country' => $this->input('country'),
            'flight_type' => $this->input('flight_type'),
            'flight_regime' => $this->input('flight_regime'),
            'flight_nature' => $this->input('flight_nature'),
            'sort' => $this->input('sort', 'name:asc'),
            'per_page' => $this->input('per_page', 15),
        ];
    }
}
