<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly array $data = [],
        public readonly ?string $link = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'code' => $this->code,
            'data' => $this->data,
            'link' => $this->link,
        ];
    }
}
