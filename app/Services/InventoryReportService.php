<?php

namespace App\Services;

use App\Repositories\Contracts\LedgerRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InventoryReportService
{
    public function __construct(
        private readonly LedgerRepositoryInterface $ledgerRepository,
    ) {
    }

    public function availableProducts(?int $storageId = null): Collection
    {
        return $this->ledgerRepository->availableProducts($storageId);
    }

    public function remainingProductsByDate(string $date, ?int $storageId = null): Collection
    {
        return $this->ledgerRepository->remainingProductsByDate(
            Carbon::parse($date)->endOfDay(),
            $storageId,
        );
    }

    public function batchProfitSummary(): Collection
    {
        return $this->ledgerRepository->batchProfitSummary();
    }
}
