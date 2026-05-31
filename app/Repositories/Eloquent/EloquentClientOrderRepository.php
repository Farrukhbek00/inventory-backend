<?php

namespace App\Repositories\Eloquent;

use App\Models\ClientOrder;
use App\Repositories\Contracts\ClientOrderRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentClientOrderRepository implements ClientOrderRepositoryInterface
{
    public function create(array $attributes): ClientOrder
    {
        return ClientOrder::query()->create($attributes);
    }

    public function createItems(ClientOrder $order, array $items): Collection
    {
        $created = collect();

        foreach ($items as $item) {
            $created->push($order->items()->create($item));
        }

        return $created;
    }

    public function findOrFail(int $orderId): ClientOrder
    {
        return ClientOrder::query()->with(['items'])->findOrFail($orderId);
    }

    public function refundableItemsByOrderAndProduct(int $orderId, int $productId): Collection
    {
        return DB::table('client_order_items as coi')
            ->join('batches as b', 'b.id', '=', 'coi.batch_id')
            ->leftJoin('sale_refund_items as sri', 'sri.client_order_item_id', '=', 'coi.id')
            ->where('coi.client_order_id', $orderId)
            ->where('coi.product_id', $productId)
            ->groupBy(
                'coi.id',
                'coi.batch_id',
                'coi.product_id',
                'coi.qty',
                'coi.unit_price',
                'coi.unit_cost',
                'b.storage_id',
            )
            ->havingRaw('(coi.qty - COALESCE(SUM(sri.qty), 0)) > 0')
            ->orderBy('coi.id')
            ->get([
                'coi.id as client_order_item_id',
                'coi.batch_id',
                'coi.product_id',
                'coi.qty as sold_qty',
                'coi.unit_price',
                'coi.unit_cost',
                'b.storage_id',
                DB::raw('COALESCE(SUM(sri.qty), 0) as refunded_qty'),
                DB::raw('(coi.qty - COALESCE(SUM(sri.qty), 0)) as remaining_qty'),
            ]);
    }
}
