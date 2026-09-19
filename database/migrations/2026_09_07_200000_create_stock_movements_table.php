<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_no')->unique();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->foreignId('from_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('to_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->string('type')->index();
            $table->string('reason')->index();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->decimal('conversion_factor', 15, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_no')->nullable();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('moved_at');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
