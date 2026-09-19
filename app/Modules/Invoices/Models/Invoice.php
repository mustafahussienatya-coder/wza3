<?php

namespace App\Modules\Invoices\Models;

use App\Modules\Collections\Models\Collection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_number',
    'customer_id',
    'sales_distributor_id',
    'warehouse_id',
    'invoice_date',
    'subtotal',
    'discount_total',
    'total_amount',
    'status',
    'confirmed_at',
    'confirmed_by',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
    'created_by',
])]
class Invoice extends Model
{
    use HasActivityLog;

    public const MOVEMENT_REFERENCE_TYPE = 'invoice';

    public const DOC_TYPE_COMPANY = 'invoice_company';

    public const DOC_TYPE_DISTRIBUTOR = 'invoice_distributor';

    protected $casts = [
        'status' => InvoiceStatus::class,
        'invoice_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesDistributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'sales_distributor_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isCompany(): bool
    {
        return $this->sales_distributor_id === null;
    }

    public function isDistributor(): bool
    {
        return $this->sales_distributor_id !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === InvoiceStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::CANCELLED;
    }

    public function scopeOwnedByDistributor(Builder $query, int $distributorId): Builder
    {
        return $query->where('sales_distributor_id', $distributorId);
    }

    public static function getPaidStatus(int $customerId): string
    {
        $totalInvoices = self::query()
            ->where('customer_id', $customerId)
            ->where('status', InvoiceStatus::CONFIRMED)
            ->sum('total_amount');

        $totalPaid = Collection::query()
            ->where('customer_id', $customerId)
            ->sum('amount');

        if (bccomp((string) $totalPaid, (string) $totalInvoices, 2) >= 0) {
            return 'paid';
        }

        if (bccomp((string) $totalPaid, '0', 2) > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    public function getOwnershipAttribute(): string
    {
        return $this->isCompany() ? 'company' : 'distributor';
    }
}
