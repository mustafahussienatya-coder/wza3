<?php

namespace App\Modules\Settlements\Models;

use App\Modules\Distributors\Models\Distributor;
use App\Modules\Settlements\Enums\SettlementPaymentMethod;
use App\Modules\Users\Models\User;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'settlement_number',
    'distributor_id',
    'amount',
    'payment_method',
    'reference_no',
    'settlement_date',
    'notes',
    'created_by',
])]
class DistributorSettlement extends Model
{
    use HasActivityLog;

    public const DOC_TYPE = 'settlement';

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => SettlementPaymentMethod::class,
        'settlement_date' => 'datetime',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
