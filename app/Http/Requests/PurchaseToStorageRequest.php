<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseToStorageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'storage_id' => ['required', 'integer', 'exists:storages,id'],
            'number' => ['nullable', 'string', 'max:64'],
            'purchased_at' => ['nullable', 'date'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'exists:products,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],
            'products.*.unit_cost' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
