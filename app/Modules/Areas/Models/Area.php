<?php

namespace App\Modules\Areas\Models;

use App\Modules\Distributors\Models\Distributor;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Area extends Model
{
    use HasActivityLog;

    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(Distributor::class, 'distributor_area');
    }
}
