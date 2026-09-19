<?php

namespace App\Modules\Invoices\Actions;

use App\Enums\UserRole;
use App\Modules\Collections\Actions\RecordCollectionAction;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Invoices\Exceptions\CustomerNotOwnedException;
use App\Modules\Invoices\Exceptions\CustomerSuspendedException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceItem;
use App\Modules\Invoices\Services\DocumentCounterService;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly DocumentCounterService $documentCounterService,
        private readonly ConfirmInvoiceAction $confirmInvoiceAction,
        private readonly RecordCollectionAction $recordCollectionAction,
    ) {}

    public function execute(array $data, User $user, bool $confirm = false, array $collections = []): Invoice
    {
        return DB::transaction(function () use ($data, $user, $confirm, $collections) {
            $isDistributor = $user->hasRole(UserRole::DISTRIBUTOR->value);
            $distributor = $isDistributor ? $user->distributor : null;

            $customer = Customer::findOrFail($data['customer_id']);

            if ($isDistributor && $customer->distributor_id !== $distributor?->id) {
                throw new CustomerNotOwnedException;
            }

            if (! $customer->isActive()) {
                throw new CustomerSuspendedException;
            }

            $salesDistributorId = $isDistributor ? $distributor?->id : $customer->distributor_id;
            $isCustodyInvoice = $salesDistributorId !== null;

            $rows = $this->invoiceService->normalizeItems($data['items'], $salesDistributorId);
            $totals = $this->invoiceService->calculateTotals($rows);

            $docType = $isCustodyInvoice ? Invoice::DOC_TYPE_DISTRIBUTOR : Invoice::DOC_TYPE_COMPANY;
            $prefix = $isCustodyInvoice ? 'D-' : 'C-';

            $invoice = Invoice::create([
                'invoice_number' => $this->documentCounterService->next($docType, $prefix),
                'customer_id' => $customer->id,
                'sales_distributor_id' => $salesDistributorId,
                'warehouse_id' => $isCustodyInvoice ? null : $data['warehouse_id'],
                'invoice_date' => $data['invoice_date'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'total_amount' => $totals['total'],
                'status' => InvoiceStatus::DRAFT,
                'created_by' => $user->id,
            ]);

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

            if ($confirm) {
                $invoice = $this->confirmInvoiceAction->execute($invoice, $user->id);

                if (! empty($collections)) {
                    foreach ($collections as &$collection) {
                        $collection['customer_id'] = $customer->id;
                    }
                    unset($collection);
                    $this->recordCollectionAction->executeMany($collections, $user);
                }
            }

            return $this->invoiceService->getOne($invoice);
        });
    }
}
