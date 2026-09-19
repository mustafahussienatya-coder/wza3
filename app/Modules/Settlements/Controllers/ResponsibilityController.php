<?php

namespace App\Modules\Settlements\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Settlements\Resources\ResponsibilityStatementResource;
use App\Modules\Settlements\Services\DistributorResponsibilityService;
use Illuminate\Http\JsonResponse;

class ResponsibilityController extends BaseController
{
    public function __construct(
        private readonly DistributorResponsibilityService $responsibilityService,
    ) {}

    public function show(Distributor $distributor): JsonResponse
    {
        $this->authorize('view', $distributor);

        $statement = $this->responsibilityService->getStatement($distributor->id);

        return $this->resourceResponse(
            new ResponsibilityStatementResource($statement),
            __('settlement_messages.responsibility_retrieved')
        );
    }
}
