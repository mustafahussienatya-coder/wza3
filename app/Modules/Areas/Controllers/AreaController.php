<?php

namespace App\Modules\Areas\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Areas\Models\Area;
use App\Modules\Areas\Requests\StoreAreaRequest;
use App\Modules\Areas\Requests\UpdateAreaRequest;
use App\Modules\Areas\Resources\AreaResource;
use App\Modules\Areas\Services\AreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends BaseController
{
    public function __construct(
        private readonly AreaService $areaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Area::class);

        $areas = $this->areaService->getAll($request->query());

        return $this->paginatedResponse($areas);
    }

    public function store(StoreAreaRequest $request): JsonResponse
    {
        $this->authorize('create', Area::class);

        $area = $this->areaService->create($request->validated());

        activity('areas')
            ->performedOn($area)
            ->event('created')
            ->withProperties($request->safe()->all())
            ->log('Area created');

        return $this->createdResponse(
            new AreaResource($area),
            __('area_messages.area_created_successfully')
        );
    }

    public function show(Area $area): JsonResponse
    {
        $this->authorize('view', $area);

        return $this->resourceResponse(
            new AreaResource($area),
            __('area_messages.area_retrieved')
        );
    }

    public function update(UpdateAreaRequest $request, Area $area): JsonResponse
    {
        $this->authorize('update', $area);

        $area = $this->areaService->update($area, $request->validated());

        activity('areas')
            ->performedOn($area)
            ->event('updated')
            ->withProperties($request->safe()->all())
            ->log('Area updated');

        return $this->successResponse(
            new AreaResource($area),
            __('area_messages.area_updated_successfully')
        );
    }

    public function destroy(Area $area): JsonResponse
    {
        $this->authorize('delete', $area);

        $this->areaService->delete($area);

        activity('areas')
            ->performedOn($area)
            ->event('deleted')
            ->log('Area deleted');

        return $this->noContentResponse(__('area_messages.area_deleted_successfully'));
    }
}
