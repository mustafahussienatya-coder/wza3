<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InventoryService
{
    public function getOverall(array $filters): LengthAwarePaginator
    {
        $query = Inventory::query()->with(['product', 'warehouse']);

        if (isset($filters['product_id']) && $filters['product_id'] !== '') {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['warehouse_id']) && $filters['warehouse_id'] !== '') {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], ['normal', 'low', 'out'], true)) {
            $query->join('products', 'products.id', '=', 'inventories.product_id');

            match ($filters['status']) {
                'out' => $query->where('inventories.quantity', '<=', 0),
                'low' => $query->where('inventories.quantity', '>', 0)
                    ->whereRaw('inventories.quantity < products.min_stock_level'),
                'normal' => $query->whereRaw('inventories.quantity >= products.min_stock_level'),
            };
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('updated_at', 'desc')->paginate($perPage);
    }

    public function getStockOnHand(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['baseUnit', 'units.unit', 'inventories.warehouse'])
            ->addSelect([
                'latest_unit_cost' => StockBatch::query()
                    ->select('unit_cost')
                    ->whereColumn('stock_batches.product_id', 'products.id')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);

        if (! empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('code', 'like', '%'.$filters['search'].'%');
            });
        }

        if (! empty($filters['warehouse_id'])) {
            $query->whereHas('inventories', function (Builder $q) use ($filters) {
                return $q->where('inventories.warehouse_id', $filters['warehouse_id']);
            });
        } else {
            $query->whereHas('inventories');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('name')->paginate($perPage);
    }

    public function getLedger(array $filters): LengthAwarePaginator
    {
        $query = StockMovement::query()
            ->with(['product.baseUnit', 'unit', 'fromWarehouse', 'toWarehouse', 'distributor.user', 'user']);

        if (isset($filters['product_id']) && $filters['product_id'] !== '') {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['warehouse_id']) && $filters['warehouse_id'] !== '') {
            $warehouseId = $filters['warehouse_id'];
            $query->where(function (Builder $q) use ($warehouseId) {
                $q->where('to_warehouse_id', $warehouseId)
                    ->orWhere('from_warehouse_id', $warehouseId);
            });
        }

        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['reason']) && $filters['reason'] !== '') {
            $query->where('reason', $filters['reason']);
        }

        if (! empty($filters['from'])) {
            $from = CarbonImmutable::parse($filters['from'])->utc();
            $query->where('moved_at', '>=', $from);
        }

        if (! empty($filters['to'])) {
            $to = CarbonImmutable::parse($filters['to'])->utc();
            $query->where('moved_at', '<=', $to);
        }

        if (! empty($filters['search'])) {
            $query->where('movement_no', 'like', '%'.$filters['search'].'%');
        }

        $perPage = $filters['per_page'] ?? 15;

        $rows = $query->orderBy('moved_at', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        $this->attachInvoiceReferences($rows->getCollection());

        return $rows;
    }

    private function attachInvoiceReferences(\Illuminate\Support\Collection $movements): void
    {
        $invoiceNumbers = $movements
            ->filter(fn (StockMovement $movement) => $movement->reference_type === 'invoice' && ! empty($movement->reference_no))
            ->pluck('reference_no')
            ->unique()
            ->values();

        if ($invoiceNumbers->isEmpty()) {
            return;
        }

        $invoiceIds = Invoice::whereIn('invoice_number', $invoiceNumbers)
            ->pluck('id', 'invoice_number');

        $movements->each(function (StockMovement $movement) use ($invoiceIds): void {
            if ($movement->reference_type === 'invoice' && $invoiceIds->has($movement->reference_no)) {
                $movement->setAttribute('invoice_reference_id', (int) $invoiceIds->get($movement->reference_no));
            }
        });
    }

    public function getBatches(array $filters): LengthAwarePaginator
    {
        $query = StockBatch::query()
            ->with(['product.baseUnit', 'warehouse'])
            ->where('product_id', $filters['product_id']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->orderBy('id', 'asc')->paginate($perPage);
    }
}
