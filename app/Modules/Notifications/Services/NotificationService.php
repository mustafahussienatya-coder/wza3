<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Notifications\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function sendToPermission(
        string $permission,
        array $excludeUserIds,
        AppNotification $notification,
    ): void {
        $users = User::query()
            ->permission($permission)
            ->active()
            ->whereNotIn('id', $excludeUserIds)
            ->get();

        $this->sendToMany($users, $notification);
    }

    public function sendToUser(?User $user, AppNotification $notification): void
    {
        if ($user === null) {
            return;
        }

        $user->notify($notification);
    }

    private function sendToMany(Collection $users, AppNotification $notification): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $notification);
        }
    }
}
