<?php

namespace App\Modules\Settlements\Controllers\My;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Settlements\Resources\ResponsibilityStatementResource;
use App\Modules\Settlements\Services\DistributorResponsibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponsibilityController extends BaseController
{
    public function __construct(
        private readonly DistributorResponsibilityService $responsibilityService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $distributorId = $request->user()->distributor?->id;

        $statement = $this->responsibilityService->getStatement($distributorId);

        return $this->resourceResponse(
            new ResponsibilityStatementResource($statement),
            __('settlement_messages.responsibility_retrieved')
        );
    }
}
