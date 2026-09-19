<?php

namespace App\Modules\Warehouses\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Warehouses\Models\Warehouse;
use App\Modules\Warehouses\Requests\StoreWarehouseRequest;
use App\Modules\Warehouses\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouses\Resources\WarehouseResource;
use App\Modules\Warehouses\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends BaseController
{
    public function __construct(
        private readonly WarehouseService $warehouseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = $this->warehouseService->getAll($request->query());

        return $this->paginatedResponse($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $this->warehouseService->create($request->validated());

        activity('warehouses')
            ->performedOn($warehouse)
            ->event('created')
            ->withProperties($request->safe()->all())
            ->log('Warehouse created');

        return $this->createdResponse(
            new WarehouseResource($warehouse),
            __('warehouse_messages.warehouse_created_successfully')
        );
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);

        $warehouse->load('manager');

        return $this->resourceResponse(
            new WarehouseResource($warehouse),
            __('warehouse_messages.warehouse_retrieved')
        );
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse = $this->warehouseService->update($warehouse, $request->validated());

        activity('warehouses')
            ->performedOn($warehouse)
            ->event('updated')
            ->withProperties($request->safe()->all())
            ->log('Warehouse updated');

        return $this->successResponse(
            new WarehouseResource($warehouse),
            __('warehouse_messages.warehouse_updated_successfully')
        );
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $this->warehouseService->delete($warehouse);

        activity('warehouses')
            ->performedOn($warehouse)
            ->event('deleted')
            ->log('Warehouse deleted');

        return $this->noContentResponse(__('warehouse_messages.warehouse_deleted_successfully'));
    }
}
