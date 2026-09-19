<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const IN_TYPES = ['issue', 'customer_return', 'transfer_in'];

    private const OUT_TYPES = ['sale', 'return_to_warehouse', 'transfer_out'];

    public function up(): void
    {
        if (DB::table('custody_batches')->exists()) {
            return;
        }

        $movements = DB::table('custody_movements')
            ->select([
                'id',
                'distributor_id',
                'product_id',
                'movement_type',
                'base_quantity',
                'selling_price',
                'reference_type',
                'reference_id',
                'created_at',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $groups = $movements->groupBy(
            static fn ($movement): string => $movement->distributor_id.'-'.$movement->product_id
        );

        $now = now();

        foreach ($groups as $group) {
            $queue = [];

            foreach ($group as $movement) {
                $base = (string) $movement->base_quantity;

                if (in_array($movement->movement_type, self::IN_TYPES, true)) {
                    $queue[] = ['movement' => $movement, 'remaining' => $base];
                } elseif (in_array($movement->movement_type, self::OUT_TYPES, true)) {
                    $need = $base;

                    foreach ($queue as &$layer) {
                        if (bccomp($need, '0', 4) <= 0) {
                            break;
                        }

                        $take = bccomp($layer['remaining'], $need, 4) >= 0
                            ? $need
                            : $layer['remaining'];

                        $layer['remaining'] = bcsub($layer['remaining'], $take, 4);
                        $need = bcsub($need, $take, 4);
                    }
                    unset($layer);
                }
            }

            $sequence = 1;

            foreach ($queue as $layer) {
                if (bccomp($layer['remaining'], '0', 4) <= 0) {
                    continue;
                }

                $movement = $layer['movement'];
                $sourceIssueId = $movement->reference_type === 'distributor_issue'
                    ? $movement->reference_id
                    : null;

                DB::table('custody_batches')->insert([
                    'distributor_id' => $movement->distributor_id,
                    'product_id' => $movement->product_id,
                    'source_issue_id' => $sourceIssueId,
                    'source_movement_id' => $movement->id,
                    'batch_no' => 'CB-D'.$movement->distributor_id.'-P'.$movement->product_id.'-'.$sequence,
                    'issued_at' => $movement->created_at ?? $now,
                    'quantity' => (string) $movement->base_quantity,
                    'remaining' => $layer['remaining'],
                    'unit_price' => (string) ($movement->selling_price ?? '0'),
                    'created_at' => $movement->created_at ?? $now,
                ]);

                $sequence++;
            }
        }
    }

    public function down(): void
    {
        // Data backfill only: reverting would delete legitimate batches created after this migration.
    }
};
