<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateClientOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'storage_id' => ['required', 'integer', 'exists:storages,id'],
            'number' => ['nullable', 'string', 'max:64'],
            'ordered_at' => ['nullable', 'date'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'exists:products,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],
            'products.*.unit_price' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
