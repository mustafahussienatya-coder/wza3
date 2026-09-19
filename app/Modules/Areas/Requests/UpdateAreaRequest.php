<?php

namespace App\Modules\Areas\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateAreaRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $areaId = $this->route('area')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('areas', 'name')->ignore($areaId)],
        ];
    }
}
