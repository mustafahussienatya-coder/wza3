<?php

namespace App\Modules\Settlements\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributorStatementEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'entry_type' => $this['entry_type'],
            'occurred_at' => $this['occurred_at'],
            'product_id' => $this['product_id'],
            'product_name' => $this['product_name'],
            'party_name' => $this['party_name'],
            'quantity' => $this['quantity'],
            'reference' => $this['reference'],
            'debit' => $this['debit'],
            'credit' => $this['credit'],
            'running_balance' => $this['running_balance'],
        ];
    }
}
