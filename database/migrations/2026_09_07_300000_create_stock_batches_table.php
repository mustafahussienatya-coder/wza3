<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements')
                ->restrictOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->string('batch_no')->unique();
            $table->date('received_at');
            $table->decimal('quantity', 15, 4);
            $table->decimal('remaining', 15, 4);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
