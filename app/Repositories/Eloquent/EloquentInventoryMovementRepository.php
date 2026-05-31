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
        $purchaseCosts = DB::table('batch_items')
            ->select('batch_id', DB::raw('SUM(qty * unit_cost) as total_purchase_cost'))
            ->groupBy('batch_id');

        $sales = DB::table('client_order_items')
            ->select('batch_id', DB::raw('SUM(qty * unit_price) as gross_sales'))
            ->groupBy('batch_id');

        $saleRefunds = DB::table('sale_refund_items as sri')
            ->join('client_order_items as coi', 'coi.id', '=', 'sri.client_order_item_id')
            ->select('coi.batch_id', DB::raw('SUM(sri.qty * sri.unit_price) as sale_refunds_amount'))
            ->groupBy('coi.batch_id');

        $purchaseRefunds = DB::table('purchase_refund_items as pri')
            ->join('purchase_refunds as pr', 'pr.id', '=', 'pri.purchase_refund_id')
            ->select('pr.batch_id', DB::raw('SUM(pri.qty * pri.unit_cost) as purchase_refunds_amount'))
            ->groupBy('pr.batch_id');

        return DB::table('batches as b')
            ->leftJoinSub($purchaseCosts, 'pc', fn ($join) => $join->on('pc.batch_id', '=', 'b.id'))
            ->leftJoinSub($sales, 's', fn ($join) => $join->on('s.batch_id', '=', 'b.id'))
            ->leftJoinSub($saleRefunds, 'sr', fn ($join) => $join->on('sr.batch_id', '=', 'b.id'))
            ->leftJoinSub($purchaseRefunds, 'pr', fn ($join) => $join->on('pr.batch_id', '=', 'b.id'))
            ->orderBy('b.purchased_at')
            ->get([
                'b.id',
                'b.number',
                'b.purchased_at',
                DB::raw('COALESCE(pc.total_purchase_cost, 0) as total_purchase_cost'),
                DB::raw('COALESCE(s.gross_sales, 0) as gross_sales'),
                DB::raw('COALESCE(sr.sale_refunds_amount, 0) as sale_refunds_amount'),
                DB::raw('COALESCE(pr.purchase_refunds_amount, 0) as purchase_refunds_amount'),
                DB::raw('(COALESCE(s.gross_sales, 0) - COALESCE(sr.sale_refunds_amount, 0)) - (COALESCE(pc.total_purchase_cost, 0) - COALESCE(pr.purchase_refunds_amount, 0)) as profit'),
            ]);
    }
}
