<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')
                ->constrained('distributors')
                ->restrictOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['distributor_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_inventories');
    }
};
