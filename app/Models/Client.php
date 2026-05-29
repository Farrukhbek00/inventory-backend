<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'phone', 'email', 'address'])]
class Client extends Model
{
    use HasFactory;

    public function orders(): HasMany
    {
        return $this->hasMany(ClientOrder::class);
    }
}
