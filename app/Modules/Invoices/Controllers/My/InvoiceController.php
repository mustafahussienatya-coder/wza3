<?php

namespace App\Modules\Invoices\Controllers\My;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Invoices\Actions\CancelInvoiceAction;
use App\Modules\Invoices\Actions\ConfirmInvoiceAction;
use App\Modules\Invoices\Actions\CreateInvoiceAction;
use App\Modules\Invoices\Actions\UpdateInvoiceAction;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Requests\CancelInvoiceRequest;
use App\Modules\Invoices\Requests\StoreInvoiceRequest;
use App\Modules\Invoices\Requests\UpdateInvoiceRequest;
use App\Modules\Invoices\Resources\InvoiceResource;
use App\Modules\Invoices\Services\InvoicePrintingService;
use App\Modules\Invoices\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePrintingService $invoicePrintingService,
        private readonly CreateInvoiceAction $createInvoiceAction,
        private readonly UpdateInvoiceAction $updateInvoiceAction,
        private readonly ConfirmInvoiceAction $confirmInvoiceAction,
        private readonly CancelInvoiceAction $cancelInvoiceAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $distributorId = $request->user()->distributor?->id;

        $rows = $this->invoiceService->getAll([
            'sales_distributor_id' => $distributorId,
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'per_page' => $request->query('per_page'),
        ]);
        $rows->through(fn ($row) => new InvoiceResource($row));

        return $this->paginatedResponse($rows, __('invoice_messages.invoices_retrieved'));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->createInvoiceAction->execute(
            $request->validated(),
            $request->user(),
            (bool) ($request->validated()['confirm'] ?? false),
            $request->validated()['collections'] ?? [],
        );

        return $this->createdResponse(new InvoiceResource($invoice), __('invoice_messages.invoice_created'));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice = $this->invoiceService->getOne($invoice);
        $invoice->setAttribute('customer_paid_status', $this->invoiceService->customerPaidStatus($invoice->customer_id));

        return $this->successResponse(new InvoiceResource($invoice), __('invoice_messages.invoice_retrieved'));
    }

    public function print(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->successResponse($this->invoicePrintingService->forPrint($invoice), __('invoice_messages.invoice_retrieved'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $invoice = $this->updateInvoiceAction->execute($invoice, $request->validated());

        return $this->successResponse(new InvoiceResource($invoice), __('invoice_messages.invoice_updated'));
    }

    public function confirm(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('confirm', $invoice);

        $invoice = $this->confirmInvoiceAction->execute($invoice, $request->user()->id);

        return $this->successResponse(new InvoiceResource($invoice), __('invoice_messages.invoice_confirmed'));
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('cancel', $invoice);

        $invoice = $this->cancelInvoiceAction->execute($invoice, $request->user()->id, $request->validated()['cancellation_reason']);

        return $this->successResponse(new InvoiceResource($invoice), __('invoice_messages.invoice_cancelled'));
    }
}
