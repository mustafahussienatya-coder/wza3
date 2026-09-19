<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Inventory\Actions\AddStockInAction;
use App\Modules\Inventory\Actions\BulkStockInAction;
use App\Modules\Inventory\Actions\CorrectStockAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Requests\IndexBatchesRequest;
use App\Modules\Inventory\Requests\IndexInventoryRequest;
use App\Modules\Inventory\Requests\IndexMovementRequest;
use App\Modules\Inventory\Requests\StoreBulkStockInRequest;
use App\Modules\Inventory\Requests\StoreStockInRequest;
use App\Modules\Inventory\Resources\InventoryResource;
use App\Modules\Inventory\Resources\StockBatchResource;
use App\Modules\Inventory\Resources\StockMovementResource;
use App\Modules\Inventory\Resources\StockOnHandResource;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Products\Models\Product;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Inventory')]
class InventoryController extends BaseController
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AddStockInAction $addStockInAction,
        private readonly BulkStockInAction $bulkStockInAction,
        private readonly CorrectStockAction $correctStockAction,
    ) {}

    /**
     * Overall inventory
     *
     * List running quantities per product/warehouse.
     *
     * @response 200 {
     *   "success": true,
     *   "data": [{"product_id": 1, "warehouse_id": 1, "quantity": "100.0000"}],
     *   "meta": {"current_page": 1, "last_page": 1, "total": 1},
     *   "links": {}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized.", "error_code": "FORBIDDEN"}
     */
    public function index(IndexInventoryRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $inventory = $this->inventoryService->getOverall($request->validated());
        $inventory->through(fn (Inventory $item) => new InventoryResource($item));

        return $this->paginatedResponse(
            $inventory,
            __('inventory_messages.inventory_retrieved')
        );
    }

    /**
     * Movements report
     *
     * List stock movements with optional filters.
     *
     * @response 200 {
     *   "success": true,
     *   "data": [{"id": 1, "movement_no": "M-..."}],
     *   "meta": {"current_page": 1, "last_page": 1, "total": 1},
     *   "links": {}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized.", "error_code": "FORBIDDEN"}
     */
    public function movements(IndexMovementRequest $request): JsonResponse
    {
        $this->authorize('viewMovements', StockMovement::class);

        $movements = $this->inventoryService->getLedger($request->validated());
        $movements->through(fn (StockMovement $movement) => new StockMovementResource($movement));

        return $this->paginatedResponse(
            $movements,
            __('inventory_messages.movements_retrieved')
        );
    }

    /**
     * Stock on hand
     *
     * List current quantities per product across warehouses, with search and
     * warehouse filters. Permissions: inventory.count.
     *
     * @response 200 {
     *   "success": true,
     *   "data": [{
     *     "product": {"id": 1, "name": "Sugar", "code": "S-1", "base_unit": {"id": 1, "name": "Kilogram", "symbol": "kg"}},
     *     "units": [{"unit_id": 1, "name": "Kilogram", "symbol": "kg", "conversion_factor": "1.0000", "selling_price": "0.00", "cost_price": "0.00"}],
     *     "quantities": [{"warehouse_id": 1, "warehouse_name": "Main", "quantity": "10.0000"}],
     *     "total": "10.0000",
     *     "latest_unit_cost": "5.00",
     *     "min_stock_level": "0.000",
     *     "status": "normal"
     *   }],
     *   "meta": {"current_page": 1, "last_page": 1, "total": 1},
     *   "links": {}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized.", "error_code": "FORBIDDEN"}
     */
    public function stockOnHand(IndexInventoryRequest $request): JsonResponse
    {
        $this->authorize('viewStockCount', Inventory::class);

        $rows = $this->inventoryService->getStockOnHand($request->validated());
        $rows->through(fn (Product $product) => new StockOnHandResource($product));

        return $this->paginatedResponse(
            $rows,
            __('inventory_messages.inventory_retrieved')
        );
    }

    /**
     * Product batches
     *
     * List the batches of a product, optionally filtered by warehouse,
     * ordered oldest first. Permissions: inventory.view.
     *
     * @queryParam product_id int required The product id. Example: 1
     * @queryParam warehouse_id int Filter by warehouse. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [{"id": 1, "batch_no": "B-...", "received_at": "2026-09-08T14:30:00+00:00", "quantity": "10.0000", "remaining": "10.0000", "unit_cost": "5.00", "warehouse": {"id": 1, "name": "Main"}, "product": {"id": 1, "name": "Sugar", "code": "S-1"}}],
     *   "meta": {"current_page": 1, "last_page": 1, "total": 1},
     *   "links": {}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized.", "error_code": "FORBIDDEN"}
     * @response 422 {"success": false, "message": "The given data was invalid.", "error_code": "VALIDATION_ERROR", "errors": {"product_id": ["The product id field is required."]}}
     */
    public function batches(IndexBatchesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Inventory::class);

        $batches = $this->inventoryService->getBatches($request->validated());
        $batches->through(fn (StockBatch $batch) => new StockBatchResource($batch));

        return $this->paginatedResponse(
            $batches,
            __('inventory_messages.batches_retrieved')
        );
    }

    /**
     * Add stock
     *
     * Receive a quantity into a warehouse. Every entry creates a new batch.
     * Permissions: inventory.adjust.
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Stock was added successfully.",
     *   "data": {"id": 1, "movement_no": "M-..."}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized.", "error_code": "FORBIDDEN"}
     * @response 422 {"success": false, "message": "The given data was invalid.", "error_code": "VALIDATION_ERROR", "errors": {"product_id": ["The product id field is required."]}}
     */
    public function storeStockIn(StoreStockInRequest $request): JsonResponse
    {
        $this->authorize('create', Inventory::class);

        $movement = $this->addStockInAction->execute(
            productId: $request->integer('product_id'),
            warehouseId: $request->integer('warehouse_id'),
            unitId: $request->integer('unit_id'),
            quantity: (string) $request->input('quantity'),
            unitPrice: (string) $request->input('unit_price'),
            reason: StockMovementReason::from((string) $request->input('reason')),
            movedAt: $request->date('moved_at'),
            description: $request->input('description'),
            userId: $request->user()->id,
        );

        activity('inventory')
            ->performedOn($movement)
            ->event('stock_in')
            ->withProperties([
                'reason' => $movement->reason->value,
                'quantity' => $movement->quantity,
            ])
            ->log('Stock added');

        return $this->createdResponse(
            new StockMovementResource($movement),
            __('inventory_messages.stock_in_created_successfully')
        );
    }

    /**
     * Bulk add stock
     *
     * Add quantities for several items in one operation.
     * Permissions: inventory.adjust.
     *
     * @bodyParam rows array required Array of items. Example: [{"product_id":1,"warehouse_id":1,"unit_id":1,"quantity":10,"unit_price":5,"reason":"purchase_order"}]
     *
     * @response 201 {
     *   "success": true,
     *   "message": "3 items were added to stock successfully.",
     *   "data": [{"id": 1, "movement_no": "M-..."}]
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized.", "error_code": "FORBIDDEN"}
     * @response 422 {"success": false, "message": "The given data was invalid.", "error_code": "VALIDATION_ERROR", "errors": {"rows.0.product_id": ["The selected product id is invalid."]}}
     */
    public function bulkStoreStockIn(StoreBulkStockInRequest $request): JsonResponse
    {
        $this->authorize('create', Inventory::class);

        $movements = $this->bulkStockInAction->execute($request->validated('rows'), $request->user()->id);

        activity('inventory')
            ->withProperties(['movement_count' => count($movements)])
            ->log('Bulk stock added');

        return $this->createdResponse(
            StockMovementResource::collection($movements),
            __('inventory_messages.stock_in_bulk_created_successfully', ['count' => count($movements)])
        );
    }

    /**
     * Correct a movement
     *
     * Create a reversing movement with reason "Entry Error".
     * Permissions: inventory.correct.
     *
     * The original movement is never edited or deleted.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Movement corrected successfully.",
     *   "data": {"id": 2, "movement_no": "M-...", "type": "stock_out", "reason": "entry_error"}
     * }
     * @response 403 {"success": false, "message": "This action is unauthorized.", "error_code": "FORBIDDEN"}
     * @response 404 {"success": false, "message": "Not Found.", "error_code": "NOT_FOUND"}
     */
    public function correctMovement(Request $request, StockMovement $stockMovement): JsonResponse
    {
        $this->authorize('correct', $stockMovement);

        $movement = $this->correctStockAction->execute($stockMovement->id, $request->user()->id);

        activity('inventory')
            ->performedOn($movement)
            ->event('corrected')
            ->withProperties([
                'original_movement_no' => $stockMovement->movement_no,
            ])
            ->log('Movement corrected');

        return $this->successResponse(
            new StockMovementResource($movement),
            __('inventory_messages.movement_corrected_successfully')
        );
    }
}
