<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'sku', 'default_sale_price'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'default_sale_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InventoryLedger::class);
    }
}
