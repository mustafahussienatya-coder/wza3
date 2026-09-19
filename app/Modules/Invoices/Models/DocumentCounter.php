<?php

namespace App\Modules\Invoices\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['doc_type', 'prefix', 'last_number'])]
class DocumentCounter extends Model
{
    protected $casts = [
        'last_number' => 'integer',
    ];
}
