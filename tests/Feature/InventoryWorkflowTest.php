<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Client;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_records_ledger_and_available_endpoint_returns_qty(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();

        $response = $this->postJson('/api/purchases', [
            'provider_id' => $provider->id,
            'storage_id' => $storage->id,
            'products' => [
                ['id' => $product->id, 'qty' => 20, 'unit_cost' => 12.50],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseCount('inventory_ledger', 1);

        $availableResponse = $this->getJson('/api/products/available?storage_id=' . $storage->id);
        $availableResponse->assertOk();
        $availableResponse->assertJsonPath('0.id', $product->id);
        $availableResponse->assertJsonPath('0.qty', 20);
    }

    public function test_order_uses_fifo_batches_automatically(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();
        $client = Client::query()->create(['name' => 'Retail Hub', 'code' => 'CL-1']);

        $firstBatch = $this->buyProduct($provider->id, $storage->id, $product->id, 5, 10.00, '2026-05-01 08:00:00');
        $secondBatch = $this->buyProduct($provider->id, $storage->id, $product->id, 10, 11.00, '2026-05-03 08:00:00');

        $orderResponse = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'storage_id' => $storage->id,
            'products' => [
                ['id' => $product->id, 'qty' => 8, 'unit_price' => 15.00],
            ],
        ]);

        $orderResponse->assertCreated();

        $sales = InventoryLedger::query()
            ->where('event_type', 'sale')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $sales);
        $this->assertSame($firstBatch->id, $sales[0]->batch_id);
        $this->assertSame(-5, $sales[0]->qty_signed);
        $this->assertSame($secondBatch->id, $sales[1]->batch_id);
        $this->assertSame(-3, $sales[1]->qty_signed);
    }

    public function test_order_can_be_created_without_storage_id(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();
        $client = Client::query()->create(['name' => 'Local Shop', 'code' => 'CL-5']);

        $this->buyProduct($provider->id, $storage->id, $product->id, 7, 10.00, '2026-05-02 08:00:00');

        $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'products' => [
                ['id' => $product->id, 'qty' => 4, 'unit_price' => 15.00],
            ],
        ])->assertCreated();

        $remainingResponse = $this->getJson('/api/storages/remaining?date=2030-12-31&storage_id=' . $storage->id);
        $remainingResponse->assertOk();
        $remainingResponse->assertJsonPath('0.qty', 3);
    }

    public function test_purchase_refund_rejects_qty_higher_than_unsold_stock(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();
        $client = Client::query()->create(['name' => 'Corner Shop', 'code' => 'CL-2']);

        $batch = $this->buyProduct($provider->id, $storage->id, $product->id, 10, 9.50);

        $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'storage_id' => $storage->id,
            'products' => [
                ['id' => $product->id, 'qty' => 4, 'unit_price' => 13.00],
            ],
        ])->assertCreated();

        $this->postJson('/api/purchases/refunds', [
            'batch_id' => $batch->id,
            'products' => [
                ['id' => $product->id, 'qty' => 7],
            ],
        ])->assertStatus(422);

        $this->postJson('/api/purchases/refunds', [
            'batch_id' => $batch->id,
            'products' => [
                ['id' => $product->id, 'qty' => 5],
            ],
        ])->assertCreated();

        $remainingResponse = $this->getJson('/api/storages/remaining?date=2030-12-31&storage_id=' . $storage->id);
        $remainingResponse->assertOk();
        $remainingResponse->assertJsonPath('0.qty', 1);
    }

    public function test_sale_refund_returns_stock_and_validates_limits(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();
        $client = Client::query()->create(['name' => 'Express Shop', 'code' => 'CL-3']);

        $this->buyProduct($provider->id, $storage->id, $product->id, 10, 9.00);

        $orderResponse = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'storage_id' => $storage->id,
            'products' => [
                ['id' => $product->id, 'qty' => 6, 'unit_price' => 13.00],
            ],
        ])->assertCreated();

        $orderId = $orderResponse->json('order_id');

        $this->postJson('/api/orders/refunds', [
            'client_order_id' => $orderId,
            'products' => [['id' => $product->id, 'qty' => 7]],
        ])->assertStatus(422);

        $this->postJson('/api/orders/refunds', [
            'client_order_id' => $orderId,
            'products' => [['id' => $product->id, 'qty' => 4]],
        ])->assertCreated();

        $remainingResponse = $this->getJson('/api/storages/remaining?date=2030-12-31&storage_id=' . $storage->id);
        $remainingResponse->assertOk();
        $remainingResponse->assertJsonPath('0.qty', 8);
    }

    public function test_batch_profit_is_calculated_correctly_with_refunds(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();
        $client = Client::query()->create(['name' => 'Market One', 'code' => 'CL-4']);

        $batch = $this->buyProduct($provider->id, $storage->id, $product->id, 10, 5.00);

        $orderResponse = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'storage_id' => $storage->id,
            'products' => [['id' => $product->id, 'qty' => 6, 'unit_price' => 8.00]],
        ])->assertCreated();

        $this->postJson('/api/orders/refunds', [
            'client_order_id' => $orderResponse->json('order_id'),
            'products' => [['id' => $product->id, 'qty' => 2]],
        ])->assertCreated();

        $this->postJson('/api/purchases/refunds', [
            'batch_id' => $batch->id,
            'products' => [['id' => $product->id, 'qty' => 1]],
        ])->assertCreated();

        $profitResponse = $this->getJson('/api/batches/profit');
        $profitResponse->assertOk();

        $profitRow = collect($profitResponse->json())->firstWhere('id', $batch->id);

        $this->assertNotNull($profitRow);
        $this->assertEquals(50.0, (float) $profitRow['total_purchase_cost']);
        $this->assertEquals(48.0, (float) $profitRow['gross_sales']);
        $this->assertEquals(16.0, (float) $profitRow['sale_refunds_amount']);
        $this->assertEquals(5.0, (float) $profitRow['purchase_refunds_amount']);
        $this->assertEquals(-13.0, (float) $profitRow['profit']);
    }

    private function makeBaseData(): array
    {
        $provider = Provider::query()->create(['name' => 'Ahmad Tea', 'code' => 'PV-1']);

        $rootCategory = Category::query()->create([
            'name' => 'Ahmad Tea',
            'provider_id' => $provider->id,
        ]);

        $leafCategory = Category::query()->create([
            'name' => 'Black Tea',
            'parent_id' => $rootCategory->id,
        ]);

        $product = Product::query()->create([
            'category_id' => $leafCategory->id,
            'name' => 'Earl Grey 500g',
            'sku' => 'TEA-500',
            'default_sale_price' => 14.00,
        ]);

        $storage = Storage::query()->create(['name' => 'Main Storage', 'code' => 'ST-1']);

        return [$provider, $storage, $product];
    }

    private function buyProduct(
        int $providerId,
        int $storageId,
        int $productId,
        int $qty,
        float $unitCost,
        ?string $purchasedAt = null,
    ): Batch {
        $payload = [
            'provider_id' => $providerId,
            'storage_id' => $storageId,
            'products' => [
                ['id' => $productId, 'qty' => $qty, 'unit_cost' => $unitCost],
            ],
        ];

        if ($purchasedAt !== null) {
            $payload['purchased_at'] = $purchasedAt;
        }

        $response = $this->postJson('/api/purchases', $payload)->assertCreated();

        return Batch::query()->findOrFail($response->json('batch_id'));
    }
}
