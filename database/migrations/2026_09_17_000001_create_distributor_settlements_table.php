<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number', 20)->unique();
            $table->foreignId('distributor_id')
                ->constrained('distributors')
                ->restrictOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method', 20);
            $table->string('reference_no')->nullable();
            $table->dateTime('settlement_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users');
            $table->timestamps();

            $table->index(['distributor_id', 'settlement_date']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_settlements');
    }
};