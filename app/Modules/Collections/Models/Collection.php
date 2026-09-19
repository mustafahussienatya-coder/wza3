<?php

namespace App\Modules\Collections\Models;

use App\Modules\Collections\Enums\PaymentMethod;
use App\Modules\Customers\Models\Customer;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Users\Models\User;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'collection_number',
    'distributor_id',
    'customer_id',
    'amount',
    'payment_method',
    'reference_no',
    'collection_date',
    'notes',
    'created_by',
])]
class Collection extends Model
{
    use HasActivityLog;

    public const MOVEMENT_REFERENCE_TYPE = 'collection';

    public const DOC_TYPE = 'collection';

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'collection_date' => 'datetime',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompany(): bool
    {
        return $this->distributor_id === null;
    }
}
