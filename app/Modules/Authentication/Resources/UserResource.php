<?php

namespace App\Modules\Authentication\Resources;

use App\Enums\UserRole;
use App\Modules\Areas\Resources\AreaResource;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = UserRole::fromValue($this->role);
        $permissions = $this->isSuperAdmin()
            ? $this->getAllPermissionsFromRoles()
            : $this->getAllPermissions()->pluck('name')->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'role' => $this->role,
            'role_label' => $role?->label(),
            'role_label_ar' => $role?->labelAr(),
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'is_distributor' => $this->isDistributor(),
            'distributor_id' => $this->distributor?->id,
            'distributor_status' => $this->distributor?->status?->value,
            'distributor' => $this->whenLoaded('distributor', function () {
                $distributor = $this->distributor;

                return [
                    'id' => $distributor->id,
                    'status' => $distributor->status?->value,
                    'status_label' => $distributor->status?->label(),
                    'status_label_ar' => $distributor->status?->labelAr(),
                    'vehicle_number' => $distributor->vehicle_number,
                    'vehicle_type' => $distributor->vehicle_type?->value,
                    'vehicle_type_label' => $distributor->vehicle_type?->label(),
                    'vehicle_type_label_ar' => $distributor->vehicle_type?->labelAr(),
                    'areas' => AreaResource::collection($distributor->areas),
                    'national_id_documents' => [
                        'front' => $distributor->documentUrl('front'),
                        'back' => $distributor->documentUrl('back'),
                    ],
                ];
            }),
            'must_change_password' => $this->must_change_password,
            'permissions' => $permissions,
            'roles' => $this->getRoleNames(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function getAllPermissionsFromRoles(): array
    {
        return Permission::query()->orderBy('name')->pluck('name')->values()->all();
    }
}
