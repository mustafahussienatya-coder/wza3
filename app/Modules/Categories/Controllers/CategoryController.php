<?php

namespace App\Modules\Categories\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Categories\Models\Category;
use App\Modules\Categories\Requests\StoreCategoryRequest;
use App\Modules\Categories\Requests\UpdateCategoryRequest;
use App\Modules\Categories\Resources\CategoryResource;
use App\Modules\Categories\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = $this->categoryService->getAll($request->query());

        return $this->paginatedResponse($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->categoryService->create($request->validated());

        activity('categories')
            ->performedOn($category)
            ->event('created')
            ->withProperties($request->safe()->all())
            ->log('Category created');

        return $this->createdResponse(
            new CategoryResource($category),
            __('category_messages.category_created_successfully')
        );
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        $category->load(['children', 'parent']);

        return $this->resourceResponse(
            new CategoryResource($category),
            __('category_messages.category_retrieved')
        );
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->categoryService->update($category, $request->validated());

        activity('categories')
            ->performedOn($category)
            ->event('updated')
            ->withProperties($request->safe()->all())
            ->log('Category updated');

        return $this->successResponse(
            new CategoryResource($category),
            __('category_messages.category_updated_successfully')
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->categoryService->delete($category);

        activity('categories')
            ->performedOn($category)
            ->event('deleted')
            ->log('Category deleted');

        return $this->noContentResponse(__('category_messages.category_deleted_successfully'));
    }
}
