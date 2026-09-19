<?php

namespace App\Modules\Invoices\Actions;

use App\Modules\Invoices\Exceptions\CannotEditConfirmedInvoiceException;
use App\Modules\Invoices\Exceptions\CustomerSuspendedException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceItem;
use App\Modules\Invoices\Services\InvoiceService;
use Illuminate\Support\Facades\DB;

class UpdateInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->isDraft()) {
                throw new CannotEditConfirmedInvoiceException;
            }

            if ($invoice->customer !== null && ! $invoice->customer->isActive()) {
                throw new CustomerSuspendedException;
            }

            $isDistributor = $invoice->isDistributor();

            $rows = $this->invoiceService->normalizeItems($data['items'], $invoice->sales_distributor_id);
            $totals = $this->invoiceService->calculateTotals($rows);

            if (! $isDistributor && isset($data['warehouse_id'])) {
                $invoice->update(['warehouse_id' => $data['warehouse_id']]);
            }

            $invoice->update([
                'invoice_date' => $data['invoice_date'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'total_amount' => $totals['total'],
            ]);

            InvoiceItem::query()->where('invoice_id', $invoice->id)->delete();

            foreach ($rows as $row) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $row['product_id'],
                    'unit_id' => $row['unit_id'],
                    'quantity' => $row['quantity'],
                    'base_quantity' => $row['base_quantity'],
                    'conversion_factor' => $row['conversion_factor'],
                    'unit_price' => $row['unit_price'],
                    'discount_amount' => $row['discount_amount'],
                    'line_total' => $row['line_total'],
                    'product_name' => $row['product_name'],
                    'unit_name' => $row['unit_name'],
                ]);
            }

            return $this->invoiceService->getOne($invoice);
        });
    }
}
