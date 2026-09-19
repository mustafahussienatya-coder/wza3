<?php

namespace App\Modules\Roles\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class UpdateRolePermissionsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }
}
