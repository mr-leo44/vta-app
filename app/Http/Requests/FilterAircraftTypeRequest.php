<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterAircraftTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name:asc,name:desc,created_at:asc,created_at:desc,updated_at:desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Le terme de recherche doit être une chaîne de caractères.',
            'search.max' => 'Le terme de recherche ne peut pas dépasser 100 caractères.',
            'sort.in' => 'Le tri invalide. Options: name:asc, name:desc, created_at:asc, created_at:desc, updated_at:desc.',
            'per_page.integer' => 'Le nombre par page doit être un nombre entier.',
            'per_page.min' => 'Le nombre par page doit être au minimum 1.',
            'per_page.max' => 'Le nombre par page ne peut pas dépasser 100.',
        ];
    }

    public function getFilters(): array
    {
        return [
            'search' => $this->input('search'),
            'sort' => $this->input('sort', 'name:asc'),
            'per_page' => $this->input('per_page', 15),
        ];
    }
}
