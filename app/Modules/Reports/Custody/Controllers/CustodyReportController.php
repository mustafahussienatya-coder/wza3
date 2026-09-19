<?php

namespace App\Modules\Reports\Custody\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Reports\Custody\Requests\FetchCustodyReportRequest;
use App\Modules\Reports\Custody\Services\CustodyReportService;

class CustodyReportController extends BaseController
{
    public function __construct(
        private readonly CustodyReportService $reportService,
    ) {}

    public function summary(FetchCustodyReportRequest $request)
    {
        $data = $this->reportService->getSummary($request->validated(), $request->user());

        return $this->successResponse($data, __('reports_messages.custody_summary_retrieved'));
    }

    public function byDistributor(FetchCustodyReportRequest $request)
    {
        $paginator = $this->reportService->getByDistributor($request->validated(), $request->user());

        return $this->paginatedResponse($paginator, __('reports_messages.custody_by_distributor_retrieved'));
    }

    public function distributorDetail(int $distributorId, FetchCustodyReportRequest $request)
    {
        $data = $this->reportService->getDistributorDetail($distributorId, $request->validated(), $request->user());

        return $this->successResponse($data, __('reports_messages.custody_distributor_detail_retrieved'));
    }

    public function byProduct(FetchCustodyReportRequest $request)
    {
        $paginator = $this->reportService->getByProduct($request->validated(), $request->user());

        return $this->paginatedResponse($paginator, __('reports_messages.custody_by_product_retrieved'));
    }

    public function productTracker(FetchCustodyReportRequest $request)
    {
        $paginator = $this->reportService->getProductTracker($request->validated(), $request->user());

        return $this->paginatedResponse($paginator, __('reports_messages.custody_product_tracker_retrieved'));
    }
}
