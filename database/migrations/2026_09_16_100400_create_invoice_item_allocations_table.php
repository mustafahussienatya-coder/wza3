<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')
                ->constrained('invoice_items')
                ->restrictOnDelete();
            $table->foreignId('custody_batch_id')
                ->nullable()
                ->constrained('custody_batches')
                ->restrictOnDelete();
            $table->foreignId('stock_batch_id')
                ->nullable()
                ->constrained('stock_batches')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['invoice_item_id', 'custody_batch_id']);
            $table->index(['invoice_item_id', 'stock_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_allocations');
    }
};
