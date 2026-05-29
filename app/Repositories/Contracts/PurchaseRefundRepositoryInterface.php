<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseRefund;
use Illuminate\Support\Collection;

interface PurchaseRefundRepositoryInterface
{
    public function create(array $attributes): PurchaseRefund;

    public function createItems(PurchaseRefund $refund, array $items): Collection;
}
