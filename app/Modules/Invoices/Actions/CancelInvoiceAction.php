<?php

namespace App\Modules\Invoices\Actions;

use App\Modules\Invoices\Exceptions\InvalidInvoiceStatusTransitionException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CancelInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $invoice, int $userId, ?string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $userId, $reason) {
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->isDraft()) {
                throw new InvalidInvoiceStatusTransitionException;
            }

            $invoice->update([
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => Carbon::now(),
                'cancellation_reason' => $reason,
            ]);

            return $this->invoiceService->getOne($invoice);
        });
    }
}