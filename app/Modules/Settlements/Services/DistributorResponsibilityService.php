<?php

namespace App\Modules\Settlements\Services;

use App\Modules\Collections\Models\Collection;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Settlements\Models\DistributorSettlement;
use Illuminate\Support\Facades\DB;

class DistributorResponsibilityService
{
    public function getStatement(int $distributorId): array
    {
        $goods = CustodyBatch::query()
            ->where('distributor_id', $distributorId)
            ->where('remaining', '>', 0)
            ->select([
                'product_id',
                DB::raw('SUM(remaining) as quantity'),
                DB::raw('SUM(CAST(remaining AS DECIMAL(15,4)) * CAST(unit_price AS DECIMAL(12,2))) as value'),
            ])
            ->groupBy('product_id')
            ->with('product:id,name')
            ->get();

        $goodsValue = bcadd($goods->reduce(fn (string $carry, $row) => bcadd($carry, (string) $row->value, 2), '0'), '0', 2);
        $custodyQuantity = bcadd($goods->reduce(fn (string $carry, $row) => bcadd($carry, (string) $row->quantity, 4), '0'), '0', 4);

        $collected = Collection::query()
            ->where('distributor_id', $distributorId)
            ->sum('amount');

        $settled = DistributorSettlement::query()
            ->where('distributor_id', $distributorId)
            ->sum('amount');

        $collectedUnsettled = bcsub((string) $collected, (string) $settled, 2);
        $total = bcadd($goodsValue, $collectedUnsettled, 2);

        $distributor = Distributor::query()
            ->whereKey($distributorId)
            ->with('user:id,name')
            ->first();

        return [
            'distributor' => $distributor !== null ? [
                'id' => $distributor->id,
                'name' => $distributor->user?->name,
            ] : null,
            'goods_value' => $goodsValue,
            'custody_quantity' => $custodyQuantity,
            'collected_unsettled' => $collectedUnsettled,
            'total' => $total,
            'per_product' => $goods->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product?->name,
                'quantity' => bcadd((string) $row->quantity, '0', 4),
                'value' => bcadd((string) $row->value, '0', 2),
            ])->values(),
        ];
    }
}
