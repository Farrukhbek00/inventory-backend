<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemainingProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'storage_id' => ['nullable', 'integer', 'exists:storages,id'],
        ];
    }
}
