<?php

namespace App\Modules\Units\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'symbol', 'decimal_places', 'is_weight', 'is_active'])]
class Unit extends Model
{
    use HasActivityLog;

    protected $casts = [
        'decimal_places' => 'integer',
        'is_weight' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isWeight(): bool
    {
        return (bool) $this->is_weight;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
