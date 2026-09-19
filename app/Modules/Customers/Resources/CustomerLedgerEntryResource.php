<?php

namespace App\Modules\Customers\Resources;

use App\Modules\Customers\Models\CustomerLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerLedgerEntry
 */
class CustomerLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'type_label_ar' => $this->type?->labelAr(),
            'debit' => $this->debit,
            'credit' => $this->credit,
            'balance_after' => $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
