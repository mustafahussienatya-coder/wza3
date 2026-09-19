<?php

namespace App\Modules\Roles\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'system' => (bool) $this->system,
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->pluck('name');
            }),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
