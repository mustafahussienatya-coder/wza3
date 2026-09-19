<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE stock_movements
            SET distributor_id = (
                SELECT distributor_issues.distributor_id
                FROM distributor_issues
                WHERE distributor_issues.issue_number = stock_movements.reference_no
                LIMIT 1
            )
            WHERE stock_movements.distributor_id IS NULL
              AND stock_movements.reference_type IN ('distributor_issue', 'custody_return')
              AND stock_movements.reference_no IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE stock_movements
            SET distributor_id = NULL
            WHERE stock_movements.reference_type IN ('distributor_issue', 'custody_return')
        SQL);
    }
};
