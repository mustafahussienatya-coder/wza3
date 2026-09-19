<?php

namespace App\Modules\Warehouses\Models;

use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Enums\WarehouseStatus;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'code', 'description', 'location', 'manager_id', 'phone', 'status'])]
class Warehouse extends Model
{
    use HasActivityLog;

    protected $casts = [
        'status' => WarehouseStatus::class,
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', WarehouseStatus::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === WarehouseStatus::ACTIVE;
    }

    public static function generateCode(): string
    {
        return 'WH-'.strtoupper(uniqid());
    }
}
