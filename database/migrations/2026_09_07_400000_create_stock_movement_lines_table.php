<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements')
                ->restrictOnDelete();
            $table->foreignId('stock_batch_id')
                ->constrained('stock_batches')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();

            $table->index('stock_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_lines');
    }
};
