<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_issue_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('correction_no')->unique();
            $table->foreignId('issue_id')
                ->constrained('distributor_issues')
                ->cascadeOnDelete();
            $table->string('status')->default('completed')->index();
            $table->foreignId('performed_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'status']);
        });

        Schema::create('distributor_issue_correction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correction_id')
                ->constrained('distributor_issue_corrections')
                ->cascadeOnDelete();
            $table->foreignId('issue_item_id')
                ->constrained('distributor_issue_items')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->string('correction_type')->index();
            $table->foreignId('original_unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->foreignId('corrected_unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->decimal('original_quantity', 15, 4);
            $table->decimal('corrected_quantity', 15, 4);
            $table->decimal('original_base_quantity', 15, 4);
            $table->decimal('corrected_base_quantity', 15, 4);
            $table->decimal('original_conversion_factor', 15, 4);
            $table->decimal('corrected_conversion_factor', 15, 4);
            $table->decimal('original_unit_price', 15, 2);
            $table->decimal('corrected_unit_price', 15, 2);
            $table->decimal('total_before', 15, 2);
            $table->decimal('total_after', 15, 2);
            $table->decimal('value_difference', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_issue_correction_items');
        Schema::dropIfExists('distributor_issue_corrections');
    }
};
