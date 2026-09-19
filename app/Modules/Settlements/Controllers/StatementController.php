<?php

namespace App\Modules\Settlements\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Settlements\Requests\IndexDistributorStatementRequest;
use App\Modules\Settlements\Resources\DistributorStatementEntryResource;
use App\Modules\Settlements\Services\DistributorStatementService;
use Illuminate\Http\JsonResponse;

class StatementController extends BaseController
{
    public function __construct(
        private readonly DistributorStatementService $statementService,
    ) {}

    public function index(IndexDistributorStatementRequest $request, Distributor $distributor): JsonResponse
    {
        $this->authorize('view', $distributor);

        $rows = $this->statementService->getStatement($distributor->id, $request->validated());
        $rows->through(fn (array $row) => new DistributorStatementEntryResource($row));

        return $this->paginatedResponse($rows, __('settlement_messages.statement_retrieved'));
    }
}
