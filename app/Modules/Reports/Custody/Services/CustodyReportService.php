<?php

namespace App\Modules\Reports\Custody\Services;

use App\Enums\UserRole;
use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Resources\CustodyMovementResource;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustodyReportService
{
    public function getSummary(array $filters, ?User $user = null): array
    {
        $this->applyUserScope($filters, $user);

        $movements = $this->buildMovementQuery($filters)->get();
        $inventories = $this->buildInventoryQuery($filters)->get();
        $priceMap = $this->buildLatestPricePerBaseUnitMap($inventories);

        $issued = $movements
            ->where('movement_type', CustodyMovementType::ISSUE)
            ->sum(fn ($m) => $this->movementValue($m));

        $returned = $movements
            ->whereIn('movement_type', [
                CustodyMovementType::CUSTOMER_RETURN,
                CustodyMovementType::RETURN_TO_WAREHOUSE,
                CustodyMovementType::TRANSFER_OUT,
            ])
            ->sum(fn ($m) => $this->movementValue($m));

        $outstanding = $inventories->sum(fn ($inv) => $this->inventoryValue($inv, $priceMap));

        $distributorsCount = Distributor::query()
            ->when(isset($filters['distributor_id']), fn ($q) => $q->whereKey($filters['distributor_id']))
            ->count();

        return [
            'total_issued_value' => round($issued, 2),
            'total_returned_value' => round($returned, 2),
            'total_outstanding_value' => round($outstanding, 2),
            'distributors_count' => $distributorsCount,
            'net_position' => round($issued - $returned - $outstanding, 2),
        ];
    }

    public function getByDistributor(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $this->applyUserScope($filters, $user);

        $perPage = $filters['per_page'] ?? 15;

        $distributors = Distributor::query()
            ->when(isset($filters['distributor_id']), fn ($q) => $q->whereKey($filters['distributor_id']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->whereHas('user', function ($uq) use ($filters) {
                    $uq->where('name', 'like', "%{$filters['search']}%");
                });
            })
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $distributorIds = $distributors->pluck('id');

        $movements = $this->buildMovementQuery($filters)
            ->whereIn('distributor_id', $distributorIds)
            ->get()
            ->groupBy('distributor_id');

        $inventories = $this->buildInventoryQuery($filters)
            ->whereIn('distributor_id', $distributorIds)
            ->get()
            ->groupBy('distributor_id');

        $allInventoryItems = collect($inventories->flatten(1));
        $priceMap = $this->buildLatestPricePerBaseUnitMap($allInventoryItems);

        $distributors->getCollection()->transform(function ($distributor) use ($movements, $inventories, $priceMap) {
            $distMovements = $movements->get($distributor->id, collect());
            $distInventories = $inventories->get($distributor->id, collect());

            $issued = $distMovements
                ->where('movement_type', CustodyMovementType::ISSUE)
                ->sum(fn ($m) => $this->movementValue($m));

            $returned = $distMovements
                ->whereIn('movement_type', [
                    CustodyMovementType::CUSTOMER_RETURN,
                    CustodyMovementType::RETURN_TO_WAREHOUSE,
                    CustodyMovementType::TRANSFER_OUT,
                ])
                ->sum(fn ($m) => $this->movementValue($m));

            $outstanding = $distInventories->sum(fn ($inv) => $this->inventoryValue($inv, $priceMap));

            $distributor->name = $distributor->user?->name;
            $distributor->phone = $distributor->user?->phone;
            $distributor->issued_value = round($issued, 2);
            $distributor->returned_value = round($returned, 2);
            $distributor->outstanding_value = round($outstanding, 2);
            $distributor->net_position = round($issued - $returned - $outstanding, 2);
            $distributor->products_count = $distInventories->count();

            return $distributor;
        });

        return $distributors;
    }

    public function getDistributorDetail(int $distributorId, array $filters, ?User $user = null): array
    {
        if ($user && $user->hasRole(UserRole::DISTRIBUTOR->value)) {
            $distributor = $user->distributor;
            if (! $distributor || $distributor->id !== $distributorId) {
                abort(403, __('errors.custody.unauthorized_report'));
            }
        }

        $distributor = Distributor::with('user')->findOrFail($distributorId);

        $movements = $this->buildMovementQuery(array_merge($filters, ['distributor_id' => $distributorId]))->get();
        $inventories = $this->buildInventoryQuery(array_merge($filters, ['distributor_id' => $distributorId]))->get();
        $priceMap = $this->buildLatestPricePerBaseUnitMap($inventories);

        $issued = $movements
            ->where('movement_type', CustodyMovementType::ISSUE)
            ->sum(fn ($m) => $this->movementValue($m));

        $returned = $movements
            ->whereIn('movement_type', [
                CustodyMovementType::CUSTOMER_RETURN,
                CustodyMovementType::RETURN_TO_WAREHOUSE,
                CustodyMovementType::TRANSFER_OUT,
            ])
            ->sum(fn ($m) => $this->movementValue($m));

        $outstanding = $inventories->sum(fn ($inv) => $this->inventoryValue($inv, $priceMap));

        $products = $inventories->map(function ($inv) use ($priceMap) {
            $key = $inv->distributor_id.':'.$inv->product_id;
            $price = $priceMap[$key] ?? 0;

            return [
                'product_id' => $inv->product_id,
                'product_name' => $inv->product?->name,
                'product_code' => $inv->product?->code,
                'quantity' => (float) $inv->quantity,
                'unit' => $inv->product?->baseUnit?->symbol,
                'unit_price' => round($price, 4),
                'value' => round((float) $inv->quantity * $price, 2),
            ];
        })->values();

        return [
            'distributor' => [
                'id' => $distributor->id,
                'name' => $distributor->user?->name,
                'phone' => $distributor->user?->phone,
            ],
            'summary' => [
                'issued_value' => round($issued, 2),
                'returned_value' => round($returned, 2),
                'outstanding_value' => round($outstanding, 2),
                'net_position' => round($issued - $returned - $outstanding, 2),
            ],
            'products' => $products,
            'movements' => CustodyMovementResource::collection($movements)->resolve(),
        ];
    }

    public function getByProduct(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $this->applyUserScope($filters, $user);

        $perPage = $filters['per_page'] ?? 15;

        $query = DistributorInventory::query()
            ->with(['product.baseUnit', 'distributor.user'])
            ->when(isset($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->whereHas('product', function ($pq) use ($filters) {
                    $pq->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', "%{$filters['search']}%");
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $rows = $query->paginate($perPage);

        $priceMap = $this->buildLatestPricePerBaseUnitMap($rows->getCollection());

        $rows->getCollection()->transform(function (DistributorInventory $inventory) use ($priceMap) {
            $key = $inventory->distributor_id.':'.$inventory->product_id;
            $price = $priceMap[$key] ?? 0;

            return [
                'product_id' => $inventory->product_id,
                'product_name' => $inventory->product?->name,
                'product_code' => $inventory->product?->code,
                'quantity' => (float) $inventory->quantity,
                'unit' => $inventory->product?->baseUnit?->symbol,
                'unit_price' => round($price, 4),
                'value' => round((float) $inventory->quantity * $price, 2),
            ];
        });

        return $rows;
    }

    public function getProductTracker(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $this->applyUserScope($filters, $user);

        $perPage = $filters['per_page'] ?? 15;

        $query = CustodyBatch::query()
            ->with(['product.baseUnit', 'distributor.user', 'sourceIssue'])
            ->when(isset($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(isset($filters['distributor_id']), fn ($q) => $q->where('distributor_id', $filters['distributor_id']))
            ->when(! empty($filters['from']), function ($q) use ($filters) {
                $from = CarbonImmutable::parse($filters['from'])->utc();
                $q->where('issued_at', '>=', $from);
            })
            ->when(! empty($filters['to']), function ($q) use ($filters) {
                $to = CarbonImmutable::parse($filters['to'])->utc();
                $q->where('issued_at', '<=', $to);
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        $rows = $query->paginate($perPage);

        $rows->getCollection()->transform(function (CustodyBatch $batch) {
            $unitPrice = (float) $batch->unit_price;
            $quantity = (float) $batch->quantity;
            $remaining = (float) $batch->remaining;

            return [
                'custody_batch_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'issue_id' => $batch->source_issue_id,
                'issue_number' => $batch->sourceIssue?->issue_number,
                'distributor_id' => $batch->distributor_id,
                'distributor_name' => $batch->distributor?->user?->name,
                'product_id' => $batch->product_id,
                'product_name' => $batch->product?->name,
                'product_code' => $batch->product?->code,
                'unit' => $batch->product?->baseUnit?->name,
                'quantity' => $quantity,
                'remaining' => $remaining,
                'sold_quantity' => round(bcsub((string) $batch->quantity, (string) $batch->remaining, 4), 4),
                'unit_price' => round($unitPrice, 2),
                'entry_value' => round($quantity * $unitPrice, 2),
                'issued_at' => $batch->issued_at?->toIso8601String(),
            ];
        });

        return $rows;
    }

    private function buildMovementQuery(array $filters): Builder
    {
        $query = CustodyMovement::query()
            ->with(['product.baseUnit', 'unit', 'performedBy', 'referenceIssue:id,issue_number', 'distributor.user'])
            ->when(isset($filters['distributor_id']), fn ($q) => $q->where('distributor_id', $filters['distributor_id']))
            ->when(isset($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(! empty($filters['movement_type']), fn ($q) => $q->where('movement_type', $filters['movement_type']))
            ->when(! empty($filters['from']), function ($q) use ($filters) {
                $from = CarbonImmutable::parse($filters['from'])->utc();
                $q->where('created_at', '>=', $from);
            })
            ->when(! empty($filters['to']), function ($q) use ($filters) {
                $to = CarbonImmutable::parse($filters['to'])->utc();
                $q->where('created_at', '<=', $to);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $query;
    }

    private function buildInventoryQuery(array $filters): Builder
    {
        $query = DistributorInventory::query()
            ->with(['product.baseUnit', 'distributor.user'])
            ->when(isset($filters['distributor_id']), fn ($q) => $q->where('distributor_id', $filters['distributor_id']))
            ->when(isset($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        return $query;
    }

    private function applyUserScope(array &$filters, ?User $user): void
    {
        if ($user && $user->hasRole(UserRole::DISTRIBUTOR->value)) {
            $distributor = $user->distributor;
            if ($distributor) {
                $filters['distributor_id'] = $distributor->id;
            }
        }
    }

    private function movementValue(CustodyMovement $movement): float
    {
        $factor = (float) $movement->conversion_factor;
        $pricePerBase = $factor > 0
            ? (float) $movement->selling_price / $factor
            : (float) $movement->selling_price;

        return (float) $movement->base_quantity * $pricePerBase;
    }

    private function inventoryValue(DistributorInventory $inventory, array $priceMap): float
    {
        $key = $inventory->distributor_id.':'.$inventory->product_id;
        $pricePerBase = $priceMap[$key] ?? 0;

        return (float) $inventory->quantity * $pricePerBase;
    }

    private function buildLatestPricePerBaseUnitMap($inventories): array
    {
        $items = $inventories instanceof Collection
            ? $inventories
            : collect($inventories);

        if ($items->isEmpty()) {
            return [];
        }

        $latestMovementIds = CustodyMovement::query()
            ->where('selling_price', '>', 0)
            ->select('distributor_id', 'product_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('distributor_id', 'product_id')
            ->orderBy('max_id')
            ->pluck('max_id');

        if ($latestMovementIds->isEmpty()) {
            return [];
        }

        return CustodyMovement::query()
            ->whereIn('id', $latestMovementIds)
            ->get()
            ->mapWithKeys(function (CustodyMovement $m) {
                $factor = (float) $m->conversion_factor;
                $pricePerBase = $factor > 0
                    ? (float) $m->selling_price / $factor
                    : (float) $m->selling_price;

                $key = $m->distributor_id.':'.$m->product_id;

                return [$key => $pricePerBase];
            })
            ->all();
    }
}
