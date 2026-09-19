<?php

namespace App\Modules\Distributors\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Requests\UpdateDistributorStatusRequest;
use App\Modules\Distributors\Resources\DistributorResource;
use App\Modules\Distributors\Services\DistributorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistributorController extends BaseController
{
    private const DOCUMENT_COLUMNS = [
        'front' => 'national_id_photo_front',
        'back' => 'national_id_photo_back',
    ];

    public function __construct(
        private readonly DistributorService $distributorService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Distributor::class);

        $distributors = $this->distributorService->getAll($request->query());
        $distributors->through(fn (Distributor $distributor) => new DistributorResource($distributor));

        return $this->paginatedResponse($distributors);
    }

    public function show(Distributor $distributor): JsonResponse
    {
        $this->authorize('view', $distributor);

        $distributor->loadMissing(['user', 'areas']);

        return $this->resourceResponse(
            new DistributorResource($distributor),
            __('distributor_messages.distributor_retrieved')
        );
    }

    public function updateStatus(UpdateDistributorStatusRequest $request, Distributor $distributor): JsonResponse
    {
        $this->authorize('updateStatus', $distributor);

        $distributor = $this->distributorService->updateStatus($distributor, $request->validated('status'));

        activity('distributors')
            ->performedOn($distributor)
            ->event('status_updated')
            ->withProperties(['status' => $distributor->status->value])
            ->log('Distributor status updated');

        return $this->successResponse(
            new DistributorResource($distributor),
            __('distributor_messages.distributor_status_updated_successfully')
        );
    }

    public function showDocument(Distributor $distributor, string $type): StreamedResponse
    {
        if (! array_key_exists($type, self::DOCUMENT_COLUMNS)) {
            abort(404);
        }

        $this->authorize('viewDocuments', $distributor);

        $column = self::DOCUMENT_COLUMNS[$type];
        $path = $distributor->{$column};

        if ($path === null || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->response($path);
    }
}
