<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('storage_id')->constrained('storages')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('client_order_id')->nullable()->constrained('client_orders')->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('document_no')->nullable();
            $table->integer('qty_signed');
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_id', 'storage_id', 'occurred_at']);
            $table->index(['batch_id', 'event_type']);
            $table->index(['client_order_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
    }
};
