<?php

namespace App\Modules\Notifications\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = is_array($this->data) ? $this->data : (json_decode((string) $this->data, true) ?: []);

        return [
            'id' => $this->id,
            'code' => $data['code'] ?? null,
            'data' => $data['data'] ?? [],
            'link' => $data['link'] ?? null,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
