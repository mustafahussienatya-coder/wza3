<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('secondary_phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('area_id')
                ->nullable()
                ->constrained('areas')
                ->nullOnDelete();
            $table->foreignId('distributor_id')
                ->nullable()
                ->constrained('distributors')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'phone']);
            $table->index(['distributor_id', 'status']);
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
