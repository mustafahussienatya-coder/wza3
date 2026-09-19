<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Areas\Models\Area;
use App\Modules\Distributors\Enums\DistributorStatus;
use App\Modules\Distributors\Enums\VehicleType;
use App\Modules\Users\Models\User;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'status', 'vehicle_number', 'vehicle_type', 'national_id_photo_front', 'national_id_photo_back'])]
class Distributor extends Model
{
    use HasActivityLog;

    protected $casts = [
        'status' => DistributorStatus::class,
        'vehicle_type' => VehicleType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'distributor_area');
    }

    public function custodyMovements(): HasMany
    {
        return $this->hasMany(CustodyMovement::class);
    }

    public function distributorInventory(): HasMany
    {
        return $this->hasMany(DistributorInventory::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DistributorIssue::class);
    }

    public function custodyBalanceFor(int $productId): string
    {
        return DistributorInventory::query()
            ->where('distributor_id', $this->id)
            ->where('product_id', $productId)
            ->value('quantity') ?? '0';
    }

    public function isActive(): bool
    {
        return $this->status === DistributorStatus::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === DistributorStatus::SUSPENDED;
    }

    public function documentUrl(string $type): ?string
    {
        $column = $type === 'front' ? 'national_id_photo_front' : 'national_id_photo_back';

        return $this->{$column}
            ? route('distributors.documents', ['distributor' => $this->id, 'type' => $type])
            : null;
    }
}
