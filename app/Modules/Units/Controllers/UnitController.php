<?php

namespace App\Modules\Units\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Units\Models\Unit;
use App\Modules\Units\Requests\StoreUnitRequest;
use App\Modules\Units\Requests\UpdateUnitRequest;
use App\Modules\Units\Resources\UnitResource;
use App\Modules\Units\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends BaseController
{
    public function __construct(
        private readonly UnitService $unitService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        $units = $this->unitService->getAll($request->query());

        return $this->paginatedResponse($units);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $unit = $this->unitService->create($request->validated());

        activity('units')
            ->performedOn($unit)
            ->event('created')
            ->withProperties($request->safe()->all())
            ->log('Unit created');

        return $this->createdResponse(
            new UnitResource($unit),
            __('unit_messages.unit_created_successfully')
        );
    }

    public function show(Unit $unit): JsonResponse
    {
        $this->authorize('view', $unit);

        return $this->resourceResponse(
            new UnitResource($unit),
            __('unit_messages.unit_retrieved')
        );
    }

    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $this->authorize('update', $unit);

        $unit = $this->unitService->update($unit, $request->validated());

        activity('units')
            ->performedOn($unit)
            ->event('updated')
            ->withProperties($request->safe()->all())
            ->log('Unit updated');

        return $this->successResponse(
            new UnitResource($unit),
            __('unit_messages.unit_updated_successfully')
        );
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $this->authorize('delete', $unit);

        $this->unitService->delete($unit);

        activity('units')
            ->performedOn($unit)
            ->event('deleted')
            ->log('Unit deleted');

        return $this->noContentResponse(__('unit_messages.unit_deleted_successfully'));
    }
}
