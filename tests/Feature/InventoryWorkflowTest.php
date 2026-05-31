<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientOrderItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_adds_products_and_available_endpoint_returns_qty(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();

        $response = $this->postJson('/api/purchases', [
            'provider_id' => $provider->id,
            'storage_id' => $storage->id,
            'products' => [
                [
                    'id' => $product->id,
                    'qty' => 20,
                    'unit_cost' => 12.50,
                ],
            ],
        ]);

        $response->assertCreated();

        $availableResponse = $this->getJson('/api/products/available?storage_id=' . $storage->id);
        $availableResponse->assertOk();
        $availableResponse->assertJsonPath('0.id', $product->id);
        $availableResponse->assertJsonPath('0.qty', 20);
    }

    public function test_order_uses_fifo_batches_automatically(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();
        $client = Client::query()->create([
            'name' => 'Retail Hub',
            'code' => 'CL-1',
        ]);

        $firstBatch = $this->buyProduct($provider->id, $storage->id, $product->id, 5, 10.00, '2026-05-01 08:00:00');
        $secondBatch = $this->buyProduct($provider->id, $storage->id, $product->id, 10, 11.00, '2026-05-03 08:00:00');

        $orderResponse = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'storage_id' => $storage->id,
            'products' => [
                [
                    'id' => $product->id,
                    'qty' => 8,
                    'unit_price' => 15.00,
                ],
            ],
        ]);

        $orderResponse->assertCreated();

        $items = ClientOrderItem::query()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame($firstBatch->id, $items[0]->batch_id);
        $this->assertSame(5, $items[0]->qty);
        $this->assertSame($secondBatch->id, $items[1]->batch_id);
        $this->assertSame(3, $items[1]->qty);
    }

    public function test_purchase_refund_rejects_qty_higher_than_unsold_stock(): void
    {
        [$provider, $storage, $product] = $this->makeBaseData();
        $client = Client::query()->create([
            'name' => 'Corner Shop',
            'code' => 'CL-2',
        ]);

        $batch = $this->buyProduct($provider->id, $storage->id, $product->id, 10, 9.50);

        $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'storage_id' => $storage->id,
            'products' => [
                [
                    'id' => $product->id,
                    'qty' => 4,
                    'unit_price' => 13.00,
                ],
            ],
        ])->assertCreated();

        $invalidRefund = $this->postJson('/api/purchases/refunds', [
            'batch_id' => $batch->id,
            'products' => [
                [
                    'id' => $product->id,
                    'qty' => 7,
                ],
            ],
        ]);

        $invalidRefund->assertStatus(422);

        $validRefund = $this->postJson('/api/purchases/refunds', [
            'batch_id' => $batch->id,
            'products' => [
                [
                    'id' => $product->id,
                    'qty' => 5,
                ],
            ],
        ]);

        $validRefund->assertCreated();

        $remainingResponse = $this->getJson('/api/storages/remaining?date=2026-05-31&storage_id=' . $storage->id);
        $remainingResponse->assertOk();
        $remainingResponse->assertJsonPath('0.qty', 1);
    }

    private function makeBaseData(): array
    {
        $provider = Provider::query()->create([
            'name' => 'Ahmad Tea',
            'code' => 'PV-1',
        ]);

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

        $storage = Storage::query()->create([
            'name' => 'Main Storage',
            'code' => 'ST-1',
        ]);

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
                [
                    'id' => $productId,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                ],
            ],
        ];

        if ($purchasedAt !== null) {
            $payload['purchased_at'] = $purchasedAt;
        }

        $response = $this->postJson('/api/purchases', $payload)->assertCreated();

        return Batch::query()->findOrFail($response->json('batch_id'));
    }
}
