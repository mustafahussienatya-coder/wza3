<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The create migration (2026_09_09_000006) defines this unique index, but in some
        // environments the table was created before it was added. Keep this idempotent so a
        // fresh install (where the index already exists) is not affected.
        if (Schema::hasIndex('distributor_issue_items', 'distributor_issue_items_product_unit_unique')) {
            return;
        }

        Schema::table('distributor_issue_items', function (Blueprint $table) {
            $table->unique(
                ['distributor_issue_id', 'product_id', 'unit_id'],
                'distributor_issue_items_product_unit_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('distributor_issue_items', function (Blueprint $table) {
            $table->dropUnique('distributor_issue_items_product_unit_unique');
        });
    }
};
