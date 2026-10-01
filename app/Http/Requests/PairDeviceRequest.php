<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PairDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pairing_code' => ['required', 'string', 'min:4', 'max:16'],
            'hardware_info' => ['nullable', 'array'],
        ];
    }
}
