<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_number')->unique();
            $table->foreignId('distributor_id')
                ->constrained('distributors')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['distributor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_issues');
    }
};
