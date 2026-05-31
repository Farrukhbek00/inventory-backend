<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundClientOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_order_id' => ['required', 'integer', 'exists:client_orders,id'],
            'number' => ['nullable', 'string', 'max:64'],
            'refunded_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'exists:products,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
