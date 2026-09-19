<?php

namespace App\Modules\Distributors\Services;

use App\Modules\Collections\Models\Collection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Settlements\Services\DistributorResponsibilityService;
use Illuminate\Support\Facades\DB;

class DistributorDashboardService
{
    public function __construct(
        private readonly DistributorResponsibilityService $responsibilityService
    ) {}

    public function getStats(int $distributorId): array
    {
        $totals = DistributorInventory::query()
            ->where('distributor_id', $distributorId)
            ->select([
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(*) as items_count'),
            ])
            ->first();

        $responsibility = $this->responsibilityService->getStatement($distributorId);

        $salesTotal = Invoice::query()
            ->ownedByDistributor($distributorId)
            ->where('status', InvoiceStatus::CONFIRMED)
            ->sum('total_amount');

        $collectedAmount = Collection::query()
            ->where('distributor_id', $distributorId)
            ->sum('amount');

        $overdueAmount = bcsub((string) $collectedAmount, (string) $salesTotal, 2) < 0
            ? bcsub((string) $salesTotal, (string) $collectedAmount, 2)
            : '0';

        $customersCount = Customer::query()
            ->where('distributor_id', $distributorId)
            ->count();

        return [
            'custody' => [
                'total_quantity' => bcadd((string) ($totals->total_quantity ?? '0'), '0', 4),
                'estimated_value' => $responsibility['goods_value'],
                'items_count' => (int) ($totals->items_count ?? 0),
            ],
            'sales_total' => bcadd((string) $salesTotal, '0', 2),
            'collected_amount' => bcadd((string) $collectedAmount, '0', 2),
            'collected_unsettled' => $responsibility['collected_unsettled'],
            'total_responsibility' => $responsibility['total'],
            'overdue_amount' => bcadd($overdueAmount, '0', 2),
            'customers_count' => $customersCount,
        ];
    }

    public function getRecentMovements(int $distributorId, int $limit = 10): array
    {
        return CustodyMovement::query()
            ->where('distributor_id', $distributorId)
            ->with(['product.baseUnit', 'unit', 'referenceIssue:id,issue_number'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (CustodyMovement $m) => [
                'id' => $m->id,
                'type' => $m->movement_type->value,
                'type_label' => $m->movement_type->labelAr(),
                'direction' => $m->movement_type->direction(),
                'product_name' => $m->product?->name,
                'quantity' => bcadd($m->base_quantity, '0', 4),
                'unit_symbol' => $m->unit?->symbol,
                'reference_number' => $m->referenceIssue?->issue_number,
                'created_at' => $m->created_at?->toISOString(),
            ])
            ->toArray();
    }
}
