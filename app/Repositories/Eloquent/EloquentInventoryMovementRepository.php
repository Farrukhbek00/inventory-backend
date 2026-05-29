<?php

namespace App\Repositories\Eloquent;

use App\Models\InventoryMovement;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentInventoryMovementRepository implements InventoryMovementRepositoryInterface
{
    public function createMany(array $rows): void
    {
        InventoryMovement::query()->insert($rows);
    }

    public function availableProducts(?int $storageId = null): Collection
    {
        $query = DB::table('inventory_movements as im')
            ->join('products as p', 'p.id', '=', 'im.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->groupBy('p.id', 'p.name', 'p.default_sale_price', 'c.name')
            ->havingRaw('SUM(im.qty) > 0')
            ->orderBy('p.name');

        if ($storageId !== null) {
            $query->where('im.storage_id', $storageId);
        }

        return $query->get([
            'p.id',
            'p.name',
            'c.name as category_name',
            'p.default_sale_price as price',
            DB::raw('SUM(im.qty) as qty'),
        ]);
    }

    public function availableQtyByProduct(int $productId, ?int $storageId = null): int
    {
        $query = DB::table('inventory_movements')
            ->where('product_id', $productId);

        if ($storageId !== null) {
            $query->where('storage_id', $storageId);
        }

        return (int) $query->sum('qty');
    }

    public function remainingProductsByDate(CarbonInterface $date, ?int $storageId = null): Collection
    {
        $query = DB::table('inventory_movements as im')
            ->join('products as p', 'p.id', '=', 'im.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('im.occurred_at', '<=', $date)
            ->groupBy('p.id', 'p.name', 'c.name')
            ->havingRaw('SUM(im.qty) > 0')
            ->orderBy('p.name');

        if ($storageId !== null) {
            $query->where('im.storage_id', $storageId);
        }

        return $query->get([
            'p.id',
            'p.name',
            'c.name as category_name',
            DB::raw('SUM(im.qty) as qty'),
        ]);
    }

    public function batchProfitSummary(): Collection
    {
        return DB::table('batches as b')
            ->join('batch_items as bi', 'bi.batch_id', '=', 'b.id')
            ->leftJoin('client_order_items as coi', 'coi.batch_id', '=', 'b.id')
            ->leftJoin('sale_refund_items as sri', 'sri.client_order_item_id', '=', 'coi.id')
            ->leftJoin('purchase_refunds as pr', 'pr.batch_id', '=', 'b.id')
            ->leftJoin('purchase_refund_items as pri', 'pri.purchase_refund_id', '=', 'pr.id')
            ->groupBy('b.id', 'b.number', 'b.purchased_at')
            ->orderBy('b.purchased_at')
            ->get([
                'b.id',
                'b.number',
                'b.purchased_at',
                DB::raw('SUM(bi.qty * bi.unit_cost) as total_purchase_cost'),
                DB::raw('COALESCE(SUM(coi.qty * coi.unit_price), 0) as gross_sales'),
                DB::raw('COALESCE(SUM(sri.qty * sri.unit_price), 0) as sale_refunds_amount'),
                DB::raw('COALESCE(SUM(pri.qty * pri.unit_cost), 0) as purchase_refunds_amount'),
                DB::raw('(COALESCE(SUM(coi.qty * coi.unit_price), 0) - COALESCE(SUM(sri.qty * sri.unit_price), 0)) - (SUM(bi.qty * bi.unit_cost) - COALESCE(SUM(pri.qty * pri.unit_cost), 0)) as profit'),
            ]);
    }
}
