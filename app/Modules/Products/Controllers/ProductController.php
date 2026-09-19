<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Products\Actions\BulkCreateProductsAction;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Requests\StoreBulkProductsRequest;
use App\Modules\Products\Requests\StoreProductRequest;
use App\Modules\Products\Requests\UpdateProductRequest;
use App\Modules\Products\Resources\ProductResource;
use App\Modules\Products\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly BulkCreateProductsAction $bulkCreateProductsAction,
    ) {}

    public function bulk(StoreBulkProductsRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $items = $request->validated()['items'];

        $hasOpening = collect($items)->contains(fn (array $item) => ! empty($item['opening']));

        if ($hasOpening) {
            $this->authorize('create', Inventory::class);
        }

        $result = $this->bulkCreateProductsAction->execute($items, (int) $request->user()->id);

        if (! empty($result['created'])) {
            activity('products')
                ->event('bulk_created')
                ->withProperties([
                    'created_count' => count($result['created']),
                    'errors_count' => count($result['errors']),
                ])
                ->log('Products bulk created');
        }

        $hasErrors = count($result['errors']) > 0;
        $message = $hasErrors
            ? __('product_messages.products_bulk_created_with_errors')
            : __('product_messages.products_bulk_created_successfully');

        return $this->successResponse([
            'created_count' => count($result['created']),
            'errors_count' => count($result['errors']),
            'created' => collect($result['created'])->map(fn (Product $product) => new ProductResource($product)),
            'errors' => $result['errors'],
        ], $message);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getAll($request->query());
        $products->through(fn (Product $product) => new ProductResource($product));

        return $this->paginatedResponse($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create($request->validated());

        activity('products')
            ->performedOn($product)
            ->event('created')
            ->withProperties($request->safe()->all())
            ->log('Product created');

        return $this->createdResponse(
            new ProductResource($product),
            __('product_messages.product_created_successfully')
        );
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['units.unit', 'category', 'baseUnit'])
            ->loadSum('inventories as total_stock', 'quantity');

        return $this->resourceResponse(
            new ProductResource($product),
            __('product_messages.product_retrieved')
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->productService->update($product, $request->validated());

        activity('products')
            ->performedOn($product)
            ->event('updated')
            ->withProperties($request->safe()->all())
            ->log('Product updated');

        return $this->successResponse(
            new ProductResource($product),
            __('product_messages.product_updated_successfully')
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        activity('products')
            ->performedOn($product)
            ->event('deleted')
            ->log('Product deleted');

        return $this->noContentResponse(__('product_messages.product_deleted_successfully'));
    }
}
