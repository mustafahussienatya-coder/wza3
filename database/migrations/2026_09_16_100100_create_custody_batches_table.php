<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custody_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')
                ->constrained('distributors')
                ->restrictOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->foreignId('source_issue_id')
                ->nullable()
                ->constrained('distributor_issues')
                ->restrictOnDelete();
            $table->foreignId('source_movement_id')
                ->nullable()
                ->constrained('custody_movements')
                ->restrictOnDelete();
            $table->string('batch_no')->unique();
            $table->dateTime('issued_at');
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('remaining', 15, 4)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['distributor_id', 'product_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_batches');
    }
};
