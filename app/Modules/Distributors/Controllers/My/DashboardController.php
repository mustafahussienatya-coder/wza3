<?php

namespace App\Modules\Distributors\Controllers\My;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Distributors\Services\DistributorDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly DistributorDashboardService $dashboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $distributorId = $request->user()->distributor->id;

        $stats = $this->dashboardService->getStats($distributorId);
        $recentMovements = $this->dashboardService->getRecentMovements($distributorId);

        return $this->successResponse([
            'stats' => $stats,
            'recent_movements' => $recentMovements,
        ], __('distributor_messages.dashboard_retrieved'));
    }
}
