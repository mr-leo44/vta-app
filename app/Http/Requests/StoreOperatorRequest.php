<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'iata_code' => 'nullable|string|max:5',
            'icao_code' => 'nullable|string|max:5',
            'country' => 'nullable|string|max:100',
            'flight_regime' => 'required|in:domestic,international',
            'flight_type' => 'required|in:regular,non_regular',
            'flight_nature' => 'required|in:commercial,non_commercial',
        ];
    }
}
