<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\Models\Invoice;

class InvoicePrintingService
{
    public function forPrint(Invoice $invoice): array
    {
        $invoice->load([
            'customer',
            'salesDistributor.user',
            'warehouse',
            'items',
            'creator',
            'confirmer',
            'canceller',
        ]);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'ownership' => $invoice->ownership,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'status_label_ar' => $invoice->status->labelAr(),
            'invoice_date' => $invoice->invoice_date?->toISOString(),
            'created_at' => $invoice->created_at?->toISOString(),
            'confirmed_at' => $invoice->confirmed_at?->toISOString(),
            'cancelled_at' => $invoice->cancelled_at?->toISOString(),
            'cancellation_reason' => $invoice->cancellation_reason,
            'paid_status' => Invoice::getPaidStatus((int) $invoice->customer_id),
            'company_name' => (string) config('app.name'),
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'phone' => $invoice->customer->phone,
            ] : null,
            'sales_distributor' => $invoice->salesDistributor ? [
                'id' => $invoice->salesDistributor->id,
                'name' => $invoice->salesDistributor->user?->name,
            ] : null,
            'warehouse' => $invoice->warehouse ? [
                'id' => $invoice->warehouse->id,
                'name' => $invoice->warehouse->name,
            ] : null,
            'created_by' => $invoice->creator?->name,
            'confirmed_by' => $invoice->confirmer?->name,
            'cancelled_by' => $invoice->canceller?->name,
            'subtotal' => (string) $invoice->subtotal,
            'discount_total' => (string) $invoice->discount_total,
            'total_amount' => (string) $invoice->total_amount,
            'items' => $invoice->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'unit_name' => $item->unit_name,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount_amount' => (string) $item->discount_amount,
                'line_total' => (string) $item->line_total,
            ])->values(),
        ];
    }
}