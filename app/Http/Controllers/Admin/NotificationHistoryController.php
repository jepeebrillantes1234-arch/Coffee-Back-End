<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationHistoryController extends Controller
{
    public function index(Request $request)
    {
        $notifications = DB::table('app_notifications')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $notifications->getCollection()->transform(function ($notification) {
            $notification->data = json_decode($notification->data ?? 'null', true) ?: [];
            $notification->related_label = null;
            if (! empty($notification->data['conversation_id'])) {
                $conversation = DB::table('support_conversations')->where('id', $notification->data['conversation_id'])->first();
                if ($conversation) {
                    $customer = $conversation->user_id
                        ? DB::table('users')->where('id', $conversation->user_id)->value('name')
                        : $conversation->guest_name;
                    $notification->related_label = ($customer ?: 'Customer').' · Support #'.$conversation->id;
                }
            } elseif (! empty($notification->data['order_id'])) {
                $order = DB::table('orders')->where('id', $notification->data['order_id'])->first();
                if ($order) $notification->related_label = ($order->order_number ?: 'Order').' · #'.$order->id;
            }
            return $notification;
        });

        $unreadCount = DB::table('app_notifications')->where('user_id', $request->user()->id)->where('is_read', false)->count();
        return view('admin.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function show(Request $request, int $notification)
    {
        $record = DB::table('app_notifications')->where('user_id', $request->user()->id)->where('id', $notification)->first();
        abort_unless($record, 404);

        if (! $record->is_read) {
            DB::table('app_notifications')->where('id', $record->id)->where('user_id', $request->user()->id)->where('is_read', false)
                ->update(['is_read' => true, 'updated_at' => now()]);
            $record->is_read = true;
            $record->updated_at = now();
        }

        $record->data = json_decode($record->data ?? 'null', true) ?: [];
        $relatedUrl = $this->relatedUrl($record->data);
        return view('admin.notifications.show', compact('record', 'relatedUrl'));
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['unread_count' => DB::table('app_notifications')->where('user_id', $request->user()->id)->where('is_read', false)->count()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function relatedUrl(array $data): ?string
    {
        if (! empty($data['conversation_id']) && DB::table('support_conversations')->where('id', $data['conversation_id'])->exists()) {
            return route('admin.support.show', $data['conversation_id']);
        }
        if (! empty($data['order_id']) && DB::table('orders')->where('id', $data['order_id'])->exists()) {
            return route('admin.orders.show', $data['order_id']);
        }
        return null;
    }
}
