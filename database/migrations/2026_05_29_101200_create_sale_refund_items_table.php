<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_refund_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_refund_id')->constrained('sale_refunds')->cascadeOnDelete();
            $table->foreignId('client_order_item_id')->constrained('client_order_items')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->timestamps();

            $table->index(['client_order_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refund_items');
    }
};
