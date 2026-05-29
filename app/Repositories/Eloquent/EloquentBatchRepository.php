<?php

namespace App\Repositories\Eloquent;

use App\Models\Batch;
use App\Repositories\Contracts\BatchRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentBatchRepository implements BatchRepositoryInterface
{
    public function create(array $attributes): Batch
    {
        return Batch::query()->create($attributes);
    }

    public function createItems(Batch $batch, array $items): Collection
    {
        $created = collect();

        foreach ($items as $item) {
            $created->push($batch->items()->create($item));
        }

        return $created;
    }

    public function findOrFail(int $batchId): Batch
    {
        return Batch::query()->with(['items'])->findOrFail($batchId);
    }

    public function availableLotsByProduct(int $productId, int $storageId): Collection
    {
        return DB::table('batch_items as bi')
            ->join('batches as b', 'b.id', '=', 'bi.batch_id')
            ->leftJoin('inventory_movements as im', function ($join): void {
                $join->on('im.batch_id', '=', 'b.id')
                    ->on('im.product_id', '=', 'bi.product_id');
            })
            ->where('bi.product_id', $productId)
            ->where('b.storage_id', $storageId)
            ->groupBy('bi.id', 'bi.batch_id', 'bi.product_id', 'bi.unit_cost', 'b.purchased_at')
            ->havingRaw('COALESCE(SUM(im.qty), 0) > 0')
            ->orderBy('b.purchased_at')
            ->orderBy('bi.id')
            ->get([
                'bi.id as batch_item_id',
                'bi.batch_id',
                'bi.product_id',
                'bi.unit_cost',
                'b.purchased_at',
                DB::raw('COALESCE(SUM(im.qty), 0) as available_qty'),
            ]);
    }
}
