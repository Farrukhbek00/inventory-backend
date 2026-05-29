<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('number')->unique();
            $table->timestamp('ordered_at');
            $table->timestamps();

            $table->index(['client_id', 'ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_orders');
    }
};
