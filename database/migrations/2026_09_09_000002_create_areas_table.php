<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('distributor_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')
                ->constrained('distributors')
                ->cascadeOnDelete();
            $table->foreignId('area_id')
                ->constrained('areas')
                ->cascadeOnDelete();
            $table->unique(['distributor_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_area');
        Schema::dropIfExists('areas');
    }
};
