<?php

namespace App\Support;

use App\Events\LiveUpdate;
use App\Models\Delivery;
use App\Models\DeliveryChatMessage;
use App\Models\Order;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Notifier
{
    public static function send(?int $userId, string $title, string $message, ?string $type = null, array $data = [], ?string $fcmType = null): void
    {
        if (!$userId) {
            return;
        }

        $id = DB::table('app_notifications')->insertGetId([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => json_encode($data),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $user = User::find($userId);
            if ($user) {
                app(FcmPushService::class)->sendToUser(
                    $user,
                    $title,
                    $message,
                    array_merge($data, ['type' => $fcmType ?? $type ?? 'general'])
                );
            }
        } catch (\Throwable $exception) {
            Log::warning('Unable to process notification delivery.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);
        }

        try {
            LiveUpdate::dispatch('notification', $id, $userId);
        } catch (\Throwable $exception) {
            Log::warning('Unable to publish notification update.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);
        }
    }

    public static function delivery(Delivery $delivery, string $status, bool $requireOnline = false): void
    {
        $delivery->loadMissing(['rider.riderProfile', 'order']);
        $rider = $delivery->rider;
        if (!$rider || $rider->role !== 'rider' || $rider->status !== 'active' || !$delivery->order) {
            return;
        }

        if ($requireOnline && (!$rider->riderProfile?->is_approved || !$rider->riderProfile?->is_available)) {
            return;
        }

        [$title, $message] = match ($status) {
            'assigned' => [
                'New Delivery Assignment',
                'You have a new delivery request. Tap to view the delivery.',
            ],
            'reassigned' => ['Delivery reassigned', "Order {$delivery->order->order_number} has been reassigned to you."],
            'cancelled' => ['Delivery cancelled', "Order {$delivery->order->order_number} was cancelled."],
            default => ['Delivery update', "There is an update about order {$delivery->order->order_number}."],
        };

        self::send((int) $rider->id, $title, $message, 'delivery', [
            'type' => 'delivery',
            'delivery_id' => (string) $delivery->id,
            'order_id' => (string) $delivery->order->id,
            'status' => $status,
            'order_status' => (string) $delivery->order->status,
        ], $status === 'assigned' ? 'delivery_assigned' : null);
    }

    public static function paymentConfirmed(Order $order): void
    {
        self::send($order->user_id, 'Payment confirmed', "Payment for order {$order->order_number} was confirmed.", 'payment', [
            'type' => 'payment',
            'event' => 'payment_confirmed',
            'order_id' => (string) $order->id,
            'order_status' => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
        ]);
    }

    public static function order(Order $order, string $status): void
    {
        $copy = match ($status) {
            'pending', 'awaiting_payment' => [
                'Order received', "Order {$order->order_number} is waiting for confirmation.",
            ],
            'confirmed' => [
                'Order Confirmed!',
                'Your order has been accepted by KJM Coffee. We will start preparing your order soon.',
            ],
            'payment_confirmed' => [
                'Payment confirmed', "Payment for order {$order->order_number} was confirmed.",
            ],
            'preparing' => ['Preparing Your Order', 'Your order is now being prepared.'],
            'ready' => ['Order Ready for Pickup', 'Your order is ready for rider pickup.'],
            'rider_assigned' => [
                'Rider assigned', "A rider has been assigned to order {$order->order_number}.",
            ],
            'picked_up' => ['Order Picked Up', 'Your rider has picked up your order.'],
            'on_the_way' => ['Your Order Is On the Way!', 'Your rider is now delivering your order.'],
            'delivered' => ['Order Delivered!', 'Your order has been delivered.'],
            'completed' => [
                'Order completed', "Order {$order->order_number} is complete.",
            ],
            'cancelled' => [
                'Order cancelled', "Order {$order->order_number} was cancelled.",
            ],
            'rejected' => [
                'Order declined', "The shop could not accept order {$order->order_number}.",
            ],
            'expired' => [
                'Order expired', "Order {$order->order_number} expired before payment was completed.",
            ],
            default => [
                'Order update', "There is an update about order {$order->order_number}.",
            ],
        };

        $statusLogId = DB::table('order_status_logs')
            ->where('order_id', $order->id)
            ->where('status', $status)
            ->latest('id')
            ->value('id');

        $data = [
            'type' => 'order',
            'order_id' => (string) $order->id,
            'status' => $status,
        ];

        if ($statusLogId !== null) {
            $data['status_log_id'] = (string) $statusLogId;
            $existing = self::findNotificationForData((int) $order->user_id, 'order', $data);
            if ($existing) {
                $existingData = json_decode($existing->data ?? '', true) ?: [];
                $deliveryStatus = $existingData['fcm_delivery_status'] ?? 'legacy_unknown';

                // Old rows have no send outcome, so conservatively avoid resending
                // them. New failed/no-token outcomes are retried on the same row.
                if (!in_array($deliveryStatus, ['failed', 'no_token', 'invalid_token'], true)) {
                    return;
                }

                self::sendOrderStatusNotification(
                    (int) $order->user_id,
                    $existing->id,
                    $copy[0],
                    $copy[1],
                    $data,
                    $existingData
                );

                return;
            }

            self::sendOrderStatusNotification(
                (int) $order->user_id,
                null,
                $copy[0],
                $copy[1],
                $data
            );

            return;
        }

        self::send($order->user_id, $copy[0], $copy[1], 'order', $data);
    }

    public static function riderChat(Delivery $delivery, DeliveryChatMessage $message): void
    {
        $delivery->loadMissing('order');
        $message->loadMissing('conversation');

        if (!$delivery->order
            || (int) $message->sender_id !== (int) $delivery->rider_id
            || (int) $message->conversation?->delivery_id !== (int) $delivery->id) {
            return;
        }

        $customerId = (int) $delivery->order->user_id;
        $data = [
            'type' => 'rider_chat',
            'event' => 'rider_chat',
            'order_id' => (string) $delivery->order->id,
            'delivery_id' => (string) $delivery->id,
            'conversation_id' => (string) $message->conversation_id,
            'message_id' => (string) $message->id,
        ];

        if (self::notificationExistsForData($customerId, 'rider_chat', $data)) {
            return;
        }

        self::send(
            $customerId,
            'New Message from Your Rider',
            'A new message is waiting in your order chat.',
            'rider_chat',
            $data
        );
    }

    private static function notificationExistsForData(int $userId, string $type, array $expected): bool
    {
        return self::findNotificationForData($userId, $type, $expected) !== null;
    }

    private static function findNotificationForData(int $userId, string $type, array $expected): ?object
    {
        $query = DB::table('app_notifications')
            ->where('user_id', $userId)
            ->where('type', $type);

        if (isset($expected['order_id'])) {
            $query->where('data->order_id', (string) $expected['order_id']);
        }

        if (isset($expected['message_id'])) {
            $query->where('data->message_id', (string) $expected['message_id']);
        }

        return $query->get(['id', 'data'])
            ->first(function ($notification) use ($expected) {
                $data = json_decode($notification->data ?? '', true);
                foreach ($expected as $key => $value) {
                    if (!array_key_exists($key, $data ?? []) || (string) $data[$key] !== (string) $value) {
                        return false;
                    }
                }

                return true;
            });
    }

    private static function sendOrderStatusNotification(
        int $userId,
        ?int $notificationId,
        string $title,
        string $message,
        array $data,
        array $existingData = []
    ): void {
        $isNew = $notificationId === null;
        $notificationData = array_merge($existingData, $data);
        $previousDeliveryStatus = $existingData['fcm_delivery_status'] ?? null;
        $notificationData['fcm_delivery_status'] = 'sending';
        $notificationData['fcm_last_attempt_at'] = now()->toIso8601String();

        if ($isNew) {
            $notificationId = DB::table('app_notifications')->insertGetId([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => 'order',
                'data' => json_encode($notificationData),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Claim a failed attempt before contacting FCM. A concurrent retry
            // then sees "sending" and cannot issue a second push for this event.
            $claimed = DB::table('app_notifications')
                ->where('id', $notificationId)
                ->where('data->fcm_delivery_status', $previousDeliveryStatus)
                ->update([
                    'data' => json_encode($notificationData),
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                return;
            }
        }

        $deliveryStatus = 'no_token';
        try {
            $user = User::find($userId);
            if ($user) {
                $deliveryStatus = app(FcmPushService::class)->sendToUser(
                    $user,
                    $title,
                    $message,
                    array_merge($data, ['type' => 'order'])
                );
            }
        } catch (\Throwable $exception) {
            // Keep diagnostics sanitized; the FCM service also logs only user ID
            // and exception class for provider failures.
            Log::warning('Unable to process order notification delivery.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);
            $deliveryStatus = 'failed';
        }

        $notificationData['fcm_delivery_status'] = $deliveryStatus;
        $notificationData['fcm_last_attempt_at'] = now()->toIso8601String();
        if ($deliveryStatus === 'sent') {
            $notificationData['fcm_sent_at'] = now()->toIso8601String();
        }

        DB::table('app_notifications')->where('id', $notificationId)->update([
            'data' => json_encode($notificationData),
            'updated_at' => now(),
        ]);

        if ($isNew) {
            try {
                LiveUpdate::dispatch('notification', $notificationId, $userId);
            } catch (\Throwable $exception) {
                Log::warning('Unable to publish order notification update.', [
                    'user_id' => $userId,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
