<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'number', 'ordered_at'])]
class ClientOrder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InventoryLedger::class);
    }
}
