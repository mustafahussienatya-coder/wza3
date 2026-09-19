<?php

namespace App\Modules\Collections\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Collections\Actions\RecordCollectionAction;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Requests\IndexCollectionRequest;
use App\Modules\Collections\Requests\StoreCollectionRequest;
use App\Modules\Collections\Resources\CollectionResource;
use App\Modules\Collections\Services\CollectionService;
use Illuminate\Http\JsonResponse;

class CollectionController extends BaseController
{
    public function __construct(
        private readonly CollectionService $collectionService,
        private readonly RecordCollectionAction $recordCollectionAction,
    ) {}

    public function index(IndexCollectionRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Collection::class);

        $rows = $this->collectionService->getAll($request->validated());
        $rows->through(fn ($row) => new CollectionResource($row));

        return $this->paginatedResponse($rows, __('collection_messages.collections_retrieved'));
    }

    public function show(Collection $collection): JsonResponse
    {
        $this->authorize('view', $collection);

        $collection = $this->collectionService->getOne($collection);

        return $this->successResponse(new CollectionResource($collection), __('collection_messages.collection_retrieved'));
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $this->authorize('create', Collection::class);

        $collection = $this->recordCollectionAction->execute($request->validated(), $request->user());

        return $this->createdResponse(new CollectionResource($collection), __('collection_messages.collection_created'));
    }
}
