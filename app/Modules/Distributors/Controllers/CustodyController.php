<?php

namespace App\Modules\Distributors\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorInventory;
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

    public function index(Request $request, Distributor $distributor): JsonResponse
    {
        $this->authorize('viewCustody', $distributor);

        $rows = $this->custodyService->getInventory($distributor->id, $request->query());
        $rows->through(fn (DistributorInventory $row) => new DistributorInventoryResource($row));

        return $this->paginatedResponse($rows, __('distributor_messages.custody_retrieved'));
    }

    public function statement(IndexCustodyStatementRequest $request, Distributor $distributor): JsonResponse
    {
        $this->authorize('viewCustody', $distributor);

        $rows = $this->custodyService->getStatement($distributor->id, $request->validated());
        $rows->through(fn (CustodyMovement $row) => new CustodyMovementResource($row));

        return $this->paginatedResponse($rows, __('distributor_messages.custody_statement_retrieved'));
    }

    public function sellable(Distributor $distributor): JsonResponse
    {
        $this->authorize('viewCustody', $distributor);

        $rows = $this->custodyService->getSellableProducts($distributor->id);

        return $this->successResponse(
            SellableProductResource::collection($rows),
            __('distributor_messages.custody_sellable_retrieved'),
        );
    }
}
