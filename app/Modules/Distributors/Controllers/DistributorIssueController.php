<?php

namespace App\Modules\Distributors\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Distributors\Actions\ApproveAndDisburseDistributorIssueAction;
use App\Modules\Distributors\Actions\ApproveDistributorIssueAction;
use App\Modules\Distributors\Actions\CancelDistributorIssueAction;
use App\Modules\Distributors\Actions\CompleteDistributorIssueAction;
use App\Modules\Distributors\Actions\CorrectDistributorIssueAction;
use App\Modules\Distributors\Actions\CreateDistributorIssueAction;
use App\Modules\Distributors\Actions\ReturnDistributorIssueAction;
use App\Modules\Distributors\Actions\SubmitDistributorIssueAction;
use App\Modules\Distributors\Actions\UpdateDistributorIssueAction;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Requests\StoreCorrectionRequest;
use App\Modules\Distributors\Requests\StoreDistributorIssueRequest;
use App\Modules\Distributors\Requests\UpdateDistributorIssueRequest;
use App\Modules\Distributors\Resources\CorrectionResource;
use App\Modules\Distributors\Resources\DistributorIssueResource;
use App\Modules\Distributors\Services\CustodyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistributorIssueController extends BaseController
{
    public function __construct(
        private readonly CustodyService $custodyService,
        private readonly CreateDistributorIssueAction $createAction,
        private readonly UpdateDistributorIssueAction $updateAction,
        private readonly SubmitDistributorIssueAction $submitAction,
        private readonly ApproveDistributorIssueAction $approveAction,
        private readonly CompleteDistributorIssueAction $completeAction,
        private readonly CancelDistributorIssueAction $cancelAction,
        private readonly ApproveAndDisburseDistributorIssueAction $approveDisburseAction,
        private readonly CorrectDistributorIssueAction $correctAction,
        private readonly ReturnDistributorIssueAction $returnAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DistributorIssue::class);

        $issues = $this->custodyService->listIssues($request->query(), $request->user());
        $issues = $issues->through(fn (DistributorIssue $issue) => new DistributorIssueResource($issue));

        return $this->paginatedResponse($issues, __('distributor_messages.issues_retrieved'));
    }

    public function store(StoreDistributorIssueRequest $request): JsonResponse
    {
        $this->authorize('create', DistributorIssue::class);

        $issue = $this->createAction->execute($request->validated(), (int) $request->user()->id);

        return $this->createdResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_created_successfully')
        );
    }

    public function show(DistributorIssue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        $issue = $this->custodyService->showIssue($issue->id);

        return $this->resourceResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_retrieved')
        );
    }

    public function update(UpdateDistributorIssueRequest $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('update', $issue);

        $issue = $this->updateAction->execute($issue, $request->validated(), (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_updated_successfully')
        );
    }

    public function submit(Request $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('issue', $issue);

        $issue = $this->submitAction->execute($issue, (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_submitted_successfully')
        );
    }

    public function approve(Request $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('issue', $issue);

        $issue = $this->approveAction->execute($issue, (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_approved_successfully')
        );
    }

    public function complete(Request $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('issue', $issue);

        $issue = $this->completeAction->execute($issue, (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_completed_successfully')
        );
    }

    public function cancel(Request $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('issue', $issue);

        $issue = $this->cancelAction->execute($issue, (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_cancelled_successfully')
        );
    }

    public function approveDisburse(Request $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('disburse', $issue);

        $issue = $this->approveDisburseAction->execute($issue, (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_approved_and_disbursed_successfully')
        );
    }

    public function correct(StoreCorrectionRequest $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('correct', $issue);

        $issue = $this->correctAction->execute($issue, $request->validated(), (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_corrected_successfully')
        );
    }

    public function corrections(DistributorIssue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        $corrections = $issue->corrections()
            ->with(['performedBy', 'items.product', 'items.originalUnit', 'items.correctedUnit'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($issue->corrections()->count() ?: 15);

        $corrections = $corrections->through(fn ($correction) => new CorrectionResource($correction));

        return $this->paginatedResponse($corrections, __('distributor_messages.corrections_retrieved'));
    }

    public function returnToWarehouse(Request $request, DistributorIssue $issue): JsonResponse
    {
        $this->authorize('returnWarehouse', $issue);

        $issue = $this->returnAction->execute($issue, (int) $request->user()->id);

        return $this->successResponse(
            new DistributorIssueResource($issue),
            __('distributor_messages.issue_returned_successfully')
        );
    }
}
