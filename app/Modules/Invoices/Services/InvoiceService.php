<?php

namespace App\Modules\Invoices\Services;

use App\Enums\UserRole;
use App\Modules\Collections\Models\Collection;
use App\Modules\Customers\Enums\LedgerTransactionType;
use App\Modules\Customers\Models\CustomerLedgerEntry;
use App\Modules\Distributors\Exceptions\InsufficientDistributorStockException;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Invoices\Exceptions\InvalidInvoiceItemException;
use App\Modules\Invoices\Exceptions\InvalidInvoiceQuantityException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductUnit;
use App\Modules\Units\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InvoiceService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['customer', 'salesDistributor.user', 'warehouse', 'creator', 'items'])
            ->withCount('items');

        $this->applyRoleScope($query);
        $this->applySearch($query, $filters);
        $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;

        $rows = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $this->attachCustomerPaidStatus($rows->items());

        return $rows;
    }

    public function getOne(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'customer',
            'salesDistributor.user',
            'warehouse',
            'creator',
            'confirmer',
            'canceller',
            'items.product.baseUnit',
            'items.unit',
            'items.allocations.custodyBatch',
            'items.allocations.stockBatch',
        ]);
    }

    /**
     * تطبيع أسطر الفاتورة والتحقق من صحة المنتج والوحدة والكمية.
     *
     * @param  int|null  $salesDistributorId  رقم الموزع عند بيع من عهدته؛ يجعل السعر read-only من العهدة
     */
    public function normalizeItems(array $items, ?int $salesDistributorId = null): array
    {
        $rows = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            if (! $product->isActive()) {
                throw new InvalidInvoiceItemException;
            }

            $productUnit = ProductUnit::query()
                ->where('product_id', $product->id)
                ->where('unit_id', $item['unit_id'])
                ->where('is_active', true)
                ->first();

            if ($productUnit === null) {
                throw new InvalidInvoiceItemException;
            }

            $quantity = bcadd((string) $item['quantity'], '0', 4);

            if (bccomp($quantity, '0', 4) <= 0) {
                throw new InvalidInvoiceQuantityException;
            }

            $factor = (string) $productUnit->conversion_factor;

            if ($salesDistributorId !== null) {
                $basePrice = $this->custodyBaseUnitPrice($salesDistributorId, $product->id);

                if ($basePrice === null) {
                    throw new InsufficientDistributorStockException(
                        product: $product->name,
                        available: '0',
                        required: bcmul($quantity, $factor, 4),
                        unit: $product->baseUnit?->name,
                    );
                }

                $unitPrice = bcmul($basePrice, $factor, 2);
                $discount = '0';
            } else {
                $unitPrice = bcadd((string) ($item['unit_price'] ?? $productUnit->selling_price ?? 0), '0', 2);
                $discount = bcadd((string) ($item['discount_amount'] ?? 0), '0', 2);
            }

            $unit = Unit::find($item['unit_id']);

            $rows[] = [
                'product_id' => $product->id,
                'unit_id' => (int) $item['unit_id'],
                'quantity' => $quantity,
                'base_quantity' => bcmul($quantity, $factor, 4),
                'conversion_factor' => $factor,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => bcsub(bcmul($quantity, $unitPrice, 2), $discount, 2),
                'product_name' => $product->name,
                'unit_name' => $unit?->name,
            ];
        }

        return $rows;
    }

    /**
     * سعر وحدة القياس الأساسية من أقدم دفعة عهدة متبقية للموزع (FIFO).
     */
    public function custodyBaseUnitPrice(int $distributorId, int $productId): ?string
    {
        $basePrice = CustodyBatch::query()
            ->where('distributor_id', $distributorId)
            ->where('product_id', $productId)
            ->where('remaining', '>', 0)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->value('unit_price');

        return $basePrice === null ? null : (string) $basePrice;
    }

    /**
     * @return array{subtotal: string, discount_total: string, total: string}
     */
    public function calculateTotals(array $rows): array
    {
        $subtotal = '0';
        $discountTotal = '0';

        foreach ($rows as $row) {
            $subtotal = bcadd($subtotal, bcmul($row['quantity'], $row['unit_price'], 2), 2);
            $discountTotal = bcadd($discountTotal, $row['discount_amount'], 2);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'total' => bcsub($subtotal, $discountTotal, 2),
        ];
    }

    /**
     * إعادة حساب الإجماليات من الأسطر المحفوظة فعلًا (تستخدم بعد confirm).
     */
    public function recalculateTotalsFor(Invoice $invoice): array
    {
        $items = $invoice->items()->get();

        $subtotal = '0';
        $discountTotal = '0';

        foreach ($items as $item) {
            $subtotal = bcadd($subtotal, bcmul((string) $item->quantity, (string) $item->unit_price, 2), 2);
            $discountTotal = bcadd($discountTotal, (string) $item->discount_amount, 2);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'total' => bcsub($subtotal, $discountTotal, 2),
        ];
    }

    public function getOutstandingBalance(int $customerId): string
    {
        return CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as outstanding')
            ->value('outstanding') ?? '0';
    }

    public function calculateBalanceAfter(int $customerId, string $delta): string
    {
        $current = CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->value('balance_after');

        return bcadd((string) ($current ?? '0'), $delta, 2);
    }

    public function createLedgerEntry(
        int $customerId,
        LedgerTransactionType $type,
        string $debit,
        string $credit,
        int $userId,
        string $notes,
    ): CustomerLedgerEntry {
        $debit = bcadd($debit, '0', 2);
        $credit = bcadd($credit, '0', 2);

        return CustomerLedgerEntry::create([
            'customer_id' => $customerId,
            'type' => $type->value,
            'debit' => $debit,
            'credit' => $credit,
            'balance_after' => $this->calculateBalanceAfter($customerId, bcsub($debit, $credit, 2)),
            'user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * حالة السداد على مستوى العميل (paid / partial / unpaid).
     */
    public function customerPaidStatus(int $customerId): string
    {
        $outstanding = (float) $this->getOutstandingBalance($customerId);

        if ($outstanding <= 0) {
            return 'paid';
        }

        $hasCollections = Collection::query()
            ->where('customer_id', $customerId)
            ->exists();

        return $hasCollections ? 'partial' : 'unpaid';
    }

    private function attachCustomerPaidStatus(array $invoices): void
    {
        $customerIds = collect($invoices)
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values();

        $paidStatus = $customerIds->mapWithKeys(fn (int $id): array => [$id => $this->customerPaidStatus($id)]);

        foreach ($invoices as $invoice) {
            $invoice->setAttribute('customer_paid_status', $paidStatus[$invoice->customer_id] ?? 'unpaid');
        }
    }

    private function applyRoleScope(Builder $query): void
    {
        $user = auth()->user();

        if ($user !== null && $user->hasRole(UserRole::DISTRIBUTOR->value)) {
            $query->where('sales_distributor_id', $user->distributor?->id);
        }
    }

    private function applySearch(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? '') !== '') {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('invoice_number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', '%'.$filters['search'].'%'));
            });
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['status']) && InvoiceStatus::tryFrom($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['ownership']) && $filters['ownership'] !== '') {
            if ($filters['ownership'] === 'company') {
                $query->whereNull('sales_distributor_id');
            } elseif ($filters['ownership'] === 'distributor') {
                $query->whereNotNull('sales_distributor_id');
            }
        }

        if (isset($filters['sales_distributor_id']) && $filters['sales_distributor_id'] !== '') {
            $query->where('sales_distributor_id', $filters['sales_distributor_id']);
        }

        if (isset($filters['customer_id']) && $filters['customer_id'] !== '') {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['from']) && $filters['from'] !== '') {
            $query->whereDate('invoice_date', '>=', $filters['from']);
        }

        if (isset($filters['to']) && $filters['to'] !== '') {
            $query->whereDate('invoice_date', '<=', $filters['to']);
        }
    }
}
