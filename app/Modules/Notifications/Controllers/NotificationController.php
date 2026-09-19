<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Notifications\Resources\NotificationResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Notifications')]
class NotificationController extends BaseController
{
    /**
     * List notifications
     *
     * Returns the authenticated user's notifications, newest first, with the
     * unread ones listed together with their count.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Notifications retrieved successfully.",
     *   "data": [{"id": "uuid", "code": "custody.approval_requested", "data": {}, "link": "/custody/disburse", "read_at": null, "created_at": "2026-09-13T10:00:00+00:00"}],
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1},
     *   "links": {}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->through(fn (mixed $notification) => new NotificationResource($notification));

        return $this->paginatedResponse($notifications, __('notifications.list_retrieved'));
    }

    /**
     * Unread count
     *
     * Returns the count of unread notifications for the authenticated user.
     * This is the cheap endpoint the frontend polls for the bell badge.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Unread notifications count retrieved.",
     *   "data": {"unread_count": 3}
     * }
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], __('notifications.unread_count_retrieved'));
    }

    /**
     * Mark one notification as read
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Notification marked as read.",
     *   "data": {"id": "uuid", "code": "custody.approval_requested", "read_at": "2026-09-13T10:00:00+00:00"}
     * }
     * @response 404 {"success": false, "message": "Not Found.", "error_code": "NOT_FOUND"}
     */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);

        if ($record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return $this->successResponse(
            new NotificationResource($record),
            __('notifications.marked_as_read')
        );
    }

    /**
     * Mark all notifications as read
     *
     * @response 200 {
     *   "success": true,
     *   "message": "All notifications marked as read.",
     *   "data": null
     * }
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse(null, __('notifications.all_marked_as_read'));
    }
}
