<?php

namespace App\Modules\Customers\Models;

use App\Modules\Areas\Models\Area;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Users\Models\User;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasActivityLog, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'secondary_phone',
        'address',
        'area_id',
        'distributor_id',
        'created_by',
        'credit_limit',
        'status',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'status' => CustomerStatus::class,
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(CustomerTransfer::class);
    }

    public function getOwnershipAttribute(): string
    {
        return $this->distributor_id ? 'distributor' : 'company';
    }

    public function getBalanceAttribute(): string
    {
        if (array_key_exists('balance', $this->attributes)) {
            return number_format((float) $this->attributes['balance'], 2, '.', '');
        }

        return number_format(
            (float) $this->ledgerEntries()->sum(DB::raw('debit - credit')),
            2,
            '.',
            ''
        );
    }

    public function getOutstandingBalanceAttribute(): string
    {
        return number_format(max((float) $this->balance, 0), 2, '.', '');
    }

    public function getAvailableCreditAttribute(): string
    {
        $outstanding = max((float) $this->balance, 0);

        return number_format(max((float) $this->credit_limit - $outstanding, 0), 2, '.', '');
    }

    public function scopeWithCurrentBalance(Builder $query): Builder
    {
        return $query->addSelect([
            'balance' => CustomerLedgerEntry::query()
                ->selectRaw('COALESCE(SUM(debit - credit), 0)')
                ->whereColumn('customer_ledger_entries.customer_id', 'customers.id'),
        ]);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::ACTIVE->value);
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::ACTIVE;
    }
}
