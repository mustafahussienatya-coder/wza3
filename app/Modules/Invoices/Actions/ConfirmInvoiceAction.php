<?php

namespace App\Modules\Invoices\Actions;

use App\Modules\Customers\Enums\LedgerTransactionType;
use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Inventory\Actions\CreateStockOutAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InvalidStockOperationException;
use App\Modules\Invoices\Exceptions\CustomerNotOwnedException;
use App\Modules\Invoices\Exceptions\CustomerSuspendedException;
use App\Modules\Invoices\Exceptions\InsufficientStockForInvoiceException;
use App\Modules\Invoices\Exceptions\InvalidInvoiceStatusTransitionException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceItem;
use App\Modules\Invoices\Models\InvoiceItemAllocation;
use App\Modules\Invoices\Services\CustodyFifoService;
use App\Modules\Invoices\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConfirmInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly CustodyFifoService $custodyFifoService,
        private readonly CreateStockOutAction $createStockOutAction,
    ) {}

    public function execute(Invoice $invoice, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->isDraft()) {
                throw new InvalidInvoiceStatusTransitionException;
            }

            $customer = $invoice->customer;

            if (! $customer->isActive()) {
                throw new CustomerSuspendedException;
            }

            if ($invoice->isDistributor()) {
                if (! $invoice->salesDistributor->isActive()) {
                    throw new InvalidStockOperationException;
                }

                if ($customer->distributor_id !== $invoice->sales_distributor_id) {
                    throw new CustomerNotOwnedException;
                }

                $this->confirmCustodyInvoice($invoice, $userId);
            } else {
                if ($invoice->warehouse === null || ! $invoice->warehouse->isActive()) {
                    throw new InvalidStockOperationException;
                }

                $this->confirmCompanyInvoice($invoice, $userId);
            }

            $totals = $this->invoiceService->recalculateTotalsFor($invoice);

            $invoice->update([
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'total_amount' => $totals['total'],
                'status' => 'confirmed',
                'confirmed_by' => $userId,
                'confirmed_at' => Carbon::now(),
            ]);

            $this->invoiceService->createLedgerEntry(
                $customer->id,
                LedgerTransactionType::SALE,
                $invoice->total_amount,
                '0',
                $userId,
                $invoice->invoice_number,
            );

            return $this->invoiceService->getOne($invoice);
        });
    }

    private function confirmCustodyInvoice(Invoice $invoice, int $userId): void
    {
        $items = $invoice->items()
            ->with(['product', 'unit'])
            ->get();

        $movingAt = Carbon::now();

        foreach ($items as $item) {
            $product = $item->product;

            $inventory = DistributorInventory::query()
                ->where('distributor_id', $invoice->sales_distributor_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            $remainingSum = (string) CustodyBatch::query()
                ->where('distributor_id', $invoice->sales_distributor_id)
                ->where('product_id', $item->product_id)
                ->where('remaining', '>', 0)
                ->sum('remaining');

            if ($inventory === null) {
                $inventory = DistributorInventory::create([
                    'distributor_id' => $invoice->sales_distributor_id,
                    'product_id' => $item->product_id,
                    'quantity' => $remainingSum,
                ]);
            } elseif (bccomp((string) $inventory->quantity, $remainingSum, 4) !== 0) {
                $inventory->update(['quantity' => $remainingSum]);
            }

            $allocations = $this->custodyFifoService->allocate(
                $invoice->sales_distributor_id,
                $item->product_id,
                (string) $item->base_quantity,
            );

            foreach ($allocations as $allocation) {
                $batch = $allocation['batch'];
                $quantity = $allocation['quantity'];
                $unitPrice = (string) $batch->unit_price;

                $batch->update([
                    'remaining' => bcsub((string) $batch->remaining, $quantity, 4),
                ]);

                $inventory->update([
                    'quantity' => bcsub((string) $inventory->quantity, $quantity, 4),
                ]);

                CustodyMovement::create([
                    'distributor_id' => $invoice->sales_distributor_id,
                    'product_id' => $product->id,
                    'movement_type' => CustodyMovementType::SALE,
                    'quantity' => $quantity,
                    'unit_id' => $product->base_unit_id,
                    'base_quantity' => $quantity,
                    'conversion_factor' => '1',
                    'selling_price' => $unitPrice,
                    'reference_type' => Invoice::MOVEMENT_REFERENCE_TYPE,
                    'reference_id' => $invoice->id,
                    'performed_by' => $userId,
                    'created_at' => $movingAt,
                ]);

                $split = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_id' => $product->base_unit_id,
                    'quantity' => $quantity,
                    'base_quantity' => $quantity,
                    'conversion_factor' => '1',
                    'unit_price' => $unitPrice,
                    'discount_amount' => '0',
                    'line_total' => bcmul($quantity, $unitPrice, 2),
                    'product_name' => $product->name,
                    'unit_name' => $product->baseUnit?->name,
                ]);

                InvoiceItemAllocation::create([
                    'invoice_item_id' => $split->id,
                    'custody_batch_id' => $batch->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ]);
            }
        }

        InvoiceItem::query()->where('invoice_id', $invoice->id)->whereIn('id', $items->pluck('id'))->delete();
    }

    private function confirmCompanyInvoice(Invoice $invoice, int $userId): void
    {
        $items = $invoice->items()
            ->with(['product', 'unit'])
            ->get();

        $movingAt = Carbon::now();

        foreach ($items as $item) {
            try {
                $movement = $this->createStockOutAction->execute(
                    productId: $item->product_id,
                    warehouseId: $invoice->warehouse_id,
                    quantity: (string) $item->base_quantity,
                    reason: StockMovementReason::SALE_ORDER,
                    type: StockMovementType::SALE,
                    unitId: $item->unit_id,
                    conversionFactor: (string) $item->conversion_factor,
                    unitPrice: (string) $item->unit_price,
                    referenceType: Invoice::MOVEMENT_REFERENCE_TYPE,
                    referenceNo: $invoice->invoice_number,
                    userId: $userId,
                    movedAt: $movingAt,
                );
            } catch (InsufficientStockException $e) {
                $context = $e->getTranslations();

                throw new InsufficientStockForInvoiceException(
                    product: $context['product'] ?? null,
                    available: $context['available'] ?? null,
                    required: $context['required'] ?? null,
                    unit: $context['unit'] ?? null,
                );
            }

            foreach ($movement->lines as $line) {
                InvoiceItemAllocation::create([
                    'invoice_item_id' => $item->id,
                    'stock_batch_id' => $line->stock_batch_id,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) $line->unit_cost,
                ]);
            }
        }
    }
}
