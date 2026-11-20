<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFcmNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 8;
    public bool $failOnTimeout = true;

    public function __construct(
        public int $notificationId,
        public int $userId,
        public string $title,
        public string $message,
        public array $data = [],
    ) {
        $this->afterCommit();
    }

    public function handle(FcmPushService $push): void
    {
        $notification = DB::table('app_notifications')->where('id', $this->notificationId)->first();
        if (!$notification) {
            return;
        }

        $notificationData = json_decode((string) $notification->data, true);
        $notificationData = is_array($notificationData) ? $notificationData : [];
        $user = User::find($this->userId);
        $status = $user ? $push->sendToUser($user, $this->title, $this->message, $this->data) : 'failed';

        $notificationData['fcm_delivery_status'] = $status;
        $notificationData['fcm_last_attempt_at'] = now()->toIso8601String();
        if ($status === 'sent') {
            $notificationData['fcm_sent_at'] = now()->toIso8601String();
        }

        DB::table('app_notifications')->where('id', $this->notificationId)->update([
            'data' => json_encode($notificationData),
            'updated_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        try {
            $notification = DB::table('app_notifications')->where('id', $this->notificationId)->first();
            if ($notification) {
                $notificationData = json_decode((string) $notification->data, true);
                $notificationData = is_array($notificationData) ? $notificationData : [];
                $notificationData['fcm_delivery_status'] = 'failed';
                $notificationData['fcm_last_attempt_at'] = now()->toIso8601String();
                DB::table('app_notifications')->where('id', $this->notificationId)->update([
                    'data' => json_encode($notificationData),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable) {
            // Keep the queue failure from affecting the already-persisted notification.
        }

        Log::warning('Queued Firebase notification failed.', [
            'notification_id' => $this->notificationId,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
