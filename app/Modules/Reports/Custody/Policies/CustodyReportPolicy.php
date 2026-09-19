<?php

namespace App\Modules\Reports\Custody\Policies;

use App\Modules\Users\Models\User;

class CustodyReportPolicy
{
    public function view(User $user): bool
    {
        return $user->can('custody.report');
    }
}
