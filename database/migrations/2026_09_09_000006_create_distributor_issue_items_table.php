<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_issue_id')
                ->constrained('distributor_issues')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('base_quantity', 15, 4);
            $table->decimal('conversion_factor', 15, 4)->default(1);

            $table->unique(
                ['distributor_issue_id', 'product_id', 'unit_id'],
                'distributor_issue_items_product_unit_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_issue_items');
    }
};
