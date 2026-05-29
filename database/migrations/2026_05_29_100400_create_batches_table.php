<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $table->foreignId('storage_id')->constrained('storages')->restrictOnDelete();
            $table->string('number')->unique();
            $table->timestamp('purchased_at');
            $table->timestamps();

            $table->index(['provider_id', 'storage_id', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
