<?php

namespace App\Repositories\Eloquent;

use App\Enums\InventoryMovementType;
use App\Models\InventoryLedger;
use App\Repositories\Contracts\LedgerRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentLedgerRepository implements LedgerRepositoryInterface
{
    public function insertMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        InventoryLedger::query()->insert($rows);
    }

    public function availableLotsForProducts(array $productIds, ?int $storageId = null): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $purchase = InventoryMovementType::Purchase->value;

        $query = DB::table('inventory_ledger as l')
            ->join('batches as b', 'b.id', '=', 'l.batch_id')
            ->whereIn('l.product_id', $productIds)
            ->groupBy('l.product_id', 'l.batch_id', 'b.storage_id', 'b.purchased_at')
            ->havingRaw('SUM(l.qty_signed) > 0')
            ->orderBy('b.purchased_at')
            ->orderBy('l.batch_id');

        if ($storageId !== null) {
            $query->where('b.storage_id', $storageId);
        }

        return $query->get([
            'l.product_id',
            'l.batch_id',
            'b.storage_id',
            'b.purchased_at',
            DB::raw("MAX(CASE WHEN l.event_type = '{$purchase}' THEN l.unit_cost END) as unit_cost"),
            DB::raw('SUM(l.qty_signed) as available_qty'),
        ]);
    }

    public function availableProducts(?int $storageId = null): Collection
    {
        $query = DB::table('inventory_ledger as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->groupBy('p.id', 'p.name', 'p.default_sale_price', 'c.name')
            ->havingRaw('SUM(l.qty_signed) > 0')
            ->orderBy('p.name');

        if ($storageId !== null) {
            $query->where('l.storage_id', $storageId);
        }

        return $query->get([
            'p.id',
            'p.name',
            'c.name as category_name',
            'p.default_sale_price as price',
            DB::raw('SUM(l.qty_signed) as qty'),
        ]);
    }

    public function remainingProductsByDate(CarbonInterface $date, ?int $storageId = null): Collection
    {
        $query = DB::table('inventory_ledger as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('l.occurred_at', '<=', $date)
            ->groupBy('p.id', 'p.name', 'c.name')
            ->havingRaw('SUM(l.qty_signed) > 0')
            ->orderBy('p.name');

        if ($storageId !== null) {
            $query->where('l.storage_id', $storageId);
        }

        return $query->get([
            'p.id',
            'p.name',
            'c.name as category_name',
            DB::raw('SUM(l.qty_signed) as qty'),
        ]);
    }

    public function batchProfitSummary(): Collection
    {
        $purchase = InventoryMovementType::Purchase->value;
        $purchaseRefund = InventoryMovementType::PurchaseRefund->value;
        $sale = InventoryMovementType::Sale->value;
        $saleRefund = InventoryMovementType::SaleRefund->value;

        return DB::table('batches as b')
            ->leftJoin('inventory_ledger as l', 'l.batch_id', '=', 'b.id')
            ->groupBy('b.id', 'b.number', 'b.purchased_at')
            ->orderBy('b.purchased_at')
            ->get([
                'b.id',
                'b.number',
                'b.purchased_at',
                DB::raw("COALESCE(SUM(CASE WHEN l.event_type = '{$purchase}' THEN l.qty_signed * l.unit_cost END), 0) as total_purchase_cost"),
                DB::raw("COALESCE(SUM(CASE WHEN l.event_type = '{$sale}' THEN -l.qty_signed * l.unit_price END), 0) as gross_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN l.event_type = '{$saleRefund}' THEN l.qty_signed * l.unit_price END), 0) as sale_refunds_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN l.event_type = '{$purchaseRefund}' THEN -l.qty_signed * l.unit_cost END), 0) as purchase_refunds_amount"),
                DB::raw("(
                    COALESCE(SUM(CASE WHEN l.event_type = '{$sale}' THEN -l.qty_signed * l.unit_price END), 0)
                    - COALESCE(SUM(CASE WHEN l.event_type = '{$saleRefund}' THEN l.qty_signed * l.unit_price END), 0)
                ) - (
                    COALESCE(SUM(CASE WHEN l.event_type = '{$purchase}' THEN l.qty_signed * l.unit_cost END), 0)
                    - COALESCE(SUM(CASE WHEN l.event_type = '{$purchaseRefund}' THEN -l.qty_signed * l.unit_cost END), 0)
                ) as profit"),
            ]);
    }

    public function availableQtyInBatch(int $batchId, int $productId): int
    {
        return (int) DB::table('inventory_ledger')
            ->where('batch_id', $batchId)
            ->where('product_id', $productId)
            ->sum('qty_signed');
    }

    public function refundableSalesByOrder(int $orderId, array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $sale = InventoryMovementType::Sale->value;
        $saleRefund = InventoryMovementType::SaleRefund->value;

        return DB::table('inventory_ledger as l')
            ->where('l.client_order_id', $orderId)
            ->whereIn('l.product_id', $productIds)
            ->whereIn('l.event_type', [$sale, $saleRefund])
            ->groupBy('l.product_id', 'l.batch_id', 'l.storage_id')
            ->havingRaw('SUM(l.qty_signed) < 0')
            ->orderBy('l.batch_id')
            ->get([
                'l.product_id',
                'l.batch_id',
                'l.storage_id',
                DB::raw("MAX(CASE WHEN l.event_type = '{$sale}' THEN l.unit_price END) as unit_price"),
                DB::raw("MAX(CASE WHEN l.event_type = '{$sale}' THEN l.unit_cost END) as unit_cost"),
                DB::raw('-1 * SUM(l.qty_signed) as remaining_qty'),
            ]);
    }
}
