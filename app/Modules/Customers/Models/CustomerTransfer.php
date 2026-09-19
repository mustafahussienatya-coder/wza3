<?php

namespace App\Modules\Customers\Models;

use App\Modules\Distributors\Models\Distributor;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTransfer extends Model
{
    protected $fillable = [
        'customer_id',
        'from_distributor_id',
        'to_distributor_id',
        'user_id',
        'reason',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromDistributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'from_distributor_id');
    }

    public function toDistributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'to_distributor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
