<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_order_id', 'number', 'refunded_at', 'reason'])]
class SaleRefund extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class, 'client_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class);
    }
}
