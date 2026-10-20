<?php

namespace App\Support;

use App\Events\LiveUpdate;
use App\Models\Order;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Support\Facades\DB;

class Notifier
{
    public static function send(?int $userId, string $title, string $message, ?string $type = null, array $data = []): void
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
                    array_merge($data, ['type' => $type ?? 'general'])
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            LiveUpdate::dispatch('notification', $id, $userId);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public static function order(Order $order, string $status): void
    {
        $copy = match ($status) {
            'pending', 'awaiting_payment' => [
                'Order received', "Order {$order->order_number} is waiting for confirmation.",
            ],
            'confirmed' => [
                'Order confirmed', "The shop has confirmed order {$order->order_number}.",
            ],
            'preparing' => [
                'Preparing your order', "The shop is preparing order {$order->order_number}.",
            ],
            'ready' => [
                'Order ready', "Order {$order->order_number} is ready for pickup or delivery.",
            ],
            'rider_assigned' => [
                'Rider assigned', "A rider has been assigned to order {$order->order_number}.",
            ],
            'picked_up', 'on_the_way' => [
                'Out for delivery', "Your rider is on the way with order {$order->order_number}.",
            ],
            'delivered' => [
                'Order delivered', "Order {$order->order_number} has been delivered.",
            ],
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

        self::send($order->user_id, $copy[0], $copy[1], 'order', [
            'type' => 'order',
            'order_id' => (string) $order->id,
            'status' => $status,
        ]);
    }
}