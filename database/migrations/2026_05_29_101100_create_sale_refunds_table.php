<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_order_id')->constrained('client_orders')->restrictOnDelete();
            $table->string('number')->unique();
            $table->timestamp('refunded_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['client_order_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
    }
};
