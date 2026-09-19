<?php

namespace App\Modules\Categories\Resources;

use App\Modules\Categories\Models\Category;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'parent' => new self($this->whenLoaded('parent')),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_label_ar' => $this->status->labelAr(),
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
