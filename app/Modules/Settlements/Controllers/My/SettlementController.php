<?php

namespace App\Modules\Settlements\Controllers\My;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Settlements\Actions\RecordSettlementAction;
use App\Modules\Settlements\Models\DistributorSettlement;
use App\Modules\Settlements\Requests\IndexSettlementRequest;
use App\Modules\Settlements\Requests\StoreSettlementRequest;
use App\Modules\Settlements\Resources\SettlementResource;
use App\Modules\Settlements\Services\SettlementService;
use Illuminate\Http\JsonResponse;

class SettlementController extends BaseController
{
    public function __construct(
        private readonly SettlementService $settlementService,
        private readonly RecordSettlementAction $recordSettlementAction,
    ) {}

    public function index(IndexSettlementRequest $request): JsonResponse
    {
        $this->authorize('viewAny', DistributorSettlement::class);

        $distributorId = $request->user()->distributor?->id;

        $rows = $this->settlementService->getAll([
            'distributor_id' => $distributorId,
            'payment_method' => $request->query('payment_method'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'per_page' => $request->query('per_page'),
        ]);
        $rows->through(fn ($row) => new SettlementResource($row));

        return $this->paginatedResponse($rows, __('settlement_messages.settlements_retrieved'));
    }

    public function show(DistributorSettlement $settlement): JsonResponse
    {
        $this->authorize('view', $settlement);

        $settlement = $this->settlementService->getOne($settlement);

        return $this->successResponse(new SettlementResource($settlement), __('settlement_messages.settlement_retrieved'));
    }

    public function store(StoreSettlementRequest $request): JsonResponse
    {
        $this->authorize('create', DistributorSettlement::class);

        $settlement = $this->recordSettlementAction->execute($request->validated(), $request->user());

        return $this->createdResponse(new SettlementResource($settlement), __('settlement_messages.settlement_created'));
    }
}
