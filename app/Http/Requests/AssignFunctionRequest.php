<?php

namespace App\Http\Requests;

use App\Enums\UserFunction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('user.assignFunction');
    }

    public function rules(): array
    {
        return [
            'function'   => ['required', Rule::enum(UserFunction::class)],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'function.required' => 'La fonction est obligatoire.',
            'function.enum'     => 'La fonction spécifiée est invalide.',
            'start_date.before_or_equal' => 'La date de début ne peut pas être dans le futur.',
        ];
    }
}
