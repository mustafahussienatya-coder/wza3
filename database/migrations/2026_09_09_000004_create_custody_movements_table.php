<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custody_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')
                ->constrained('distributors')
                ->restrictOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->string('movement_type')->index();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->decimal('base_quantity', 15, 4);
            $table->decimal('conversion_factor', 15, 4)->default(1);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['distributor_id', 'movement_type']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_movements');
    }
};
