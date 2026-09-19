<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custody_movements', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 4)->nullable()->after('conversion_factor');
        });
    }

    public function down(): void
    {
        Schema::table('custody_movements', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }
};
