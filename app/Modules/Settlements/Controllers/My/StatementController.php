<?php

namespace App\Modules\Settlements\Controllers\My;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Settlements\Requests\IndexDistributorStatementRequest;
use App\Modules\Settlements\Resources\DistributorStatementEntryResource;
use App\Modules\Settlements\Services\DistributorStatementService;
use Illuminate\Http\JsonResponse;

class StatementController extends BaseController
{
    public function __construct(
        private readonly DistributorStatementService $statementService,
    ) {}

    public function index(IndexDistributorStatementRequest $request): JsonResponse
    {
        $distributorId = $request->user()->distributor?->id;

        $rows = $this->statementService->getStatement($distributorId, $request->validated());
        $rows->through(fn (array $row) => new DistributorStatementEntryResource($row));

        return $this->paginatedResponse($rows, __('settlement_messages.statement_retrieved'));
    }
}
