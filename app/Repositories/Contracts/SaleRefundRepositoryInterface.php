<?php

namespace App\Repositories\Contracts;

use App\Models\SaleRefund;
use Illuminate\Support\Collection;

interface SaleRefundRepositoryInterface
{
    public function create(array $attributes): SaleRefund;

    public function createItems(SaleRefund $refund, array $items): Collection;
}
