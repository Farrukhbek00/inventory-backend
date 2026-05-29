<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->string('number')->unique();
            $table->timestamp('refunded_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_refunds');
    }
};
