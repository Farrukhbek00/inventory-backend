<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_refund_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_refund_id')->constrained('purchase_refunds')->cascadeOnDelete();
            $table->foreignId('batch_item_id')->constrained('batch_items')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_cost', 14, 2);
            $table->timestamps();

            $table->index(['batch_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_refund_items');
    }
};
