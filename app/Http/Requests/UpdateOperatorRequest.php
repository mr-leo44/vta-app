<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'sigle' => 'sometimes|required|string|max:10',
            'iata_code' => 'nullable|string|max:5',
            'icao_code' => 'nullable|string|max:5',
            'country' => 'nullable|string|max:100',
            'flight_type' => 'sometimes|required|in:regular,non_regular',
            'flight_nature' => 'sometimes|required|in:commercial,non_commercial',
        ];
    }
}
