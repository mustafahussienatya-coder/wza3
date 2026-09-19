<?php

namespace App\Modules\Categories\Models;

use App\Modules\Categories\Enums\CategoryStatus;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'parent_id', 'status'])]
class Category extends Model
{
    use HasActivityLog;

    protected $casts = [
        'status' => CategoryStatus::class,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', CategoryStatus::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === CategoryStatus::ACTIVE;
    }

    public static function generateCode(): string
    {
        return 'CAT-'.strtoupper(uniqid());
    }
}
