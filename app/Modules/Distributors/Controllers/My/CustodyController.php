<?php

namespace App\Modules\Distributors\Controllers\My;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Distributors\Requests\IndexCustodyStatementRequest;
use App\Modules\Distributors\Resources\CustodyMovementResource;
use App\Modules\Distributors\Resources\DistributorInventoryResource;
use App\Modules\Distributors\Resources\SellableProductResource;
use App\Modules\Distributors\Services\CustodyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustodyController extends BaseController
{
    public function __construct(
        private readonly CustodyService $custodyService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $distributorId = $request->user()->distributor->id;

        $rows = $this->custodyService->getInventory($distributorId, $request->query());
        $rows->through(fn ($row) => new DistributorInventoryResource($row));

        return $this->paginatedResponse($rows, __('distributor_messages.custody_retrieved'));
    }

    public function sellable(Request $request): JsonResponse
    {
        $distributorId = $request->user()->distributor->id;

        $rows = $this->custodyService->getSellableProducts($distributorId);

        return $this->successResponse(
            SellableProductResource::collection($rows),
            __('distributor_messages.custody_sellable_retrieved'),
        );
    }

    public function statement(IndexCustodyStatementRequest $request): JsonResponse
    {
        $distributorId = $request->user()->distributor->id;

        $rows = $this->custodyService->getStatement($distributorId, $request->validated());
        $rows->through(fn ($row) => new CustodyMovementResource($row));

        return $this->paginatedResponse($rows, __('distributor_messages.custody_statement_retrieved'));
    }
}
