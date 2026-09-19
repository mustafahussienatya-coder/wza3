<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributor_issue_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->default(0)->after('conversion_factor');
        });
    }

    public function down(): void
    {
        Schema::table('distributor_issue_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
