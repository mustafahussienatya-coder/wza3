<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('collection_number', 20)->unique();
            $table->foreignId('distributor_id')
                ->nullable()
                ->constrained('distributors')
                ->restrictOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method', 20);
            $table->string('reference_no')->nullable();
            $table->dateTime('collection_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users');
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index('distributor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
