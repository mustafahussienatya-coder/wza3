<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->decimal('conversion_factor', 15, 4);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->string('barcode')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unique(['product_id', 'unit_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
