<?php

namespace App\Http\Controllers\Api;

use App\Events\LiveUpdate;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryChatConversation;
use App\Models\DeliveryChatMessage;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderChatController extends Controller
{
    use Responds;

    public function customerConversation(Request $request, int $orderId)
    {
        $delivery = Delivery::with(['order.user', 'rider'])->whereHas('order', function ($query) use ($request, $orderId) {
            $query->whereKey($orderId)->where('user_id', $request->user()->id);
        })->firstOrFail();

        return $this->open($delivery);
    }

    public function riderConversation(Request $request, int $deliveryId)
    {
        $delivery = Delivery::with(['order.user', 'rider'])
            ->where('rider_id', $request->user()->id)
            ->findOrFail($deliveryId);

        return $this->open($delivery);
    }

    public function customerSend(Request $request, int $orderId)
    {
        $delivery = Delivery::with('order.user', 'rider')->whereHas('order', function ($query) use ($request, $orderId) {
            $query->whereKey($orderId)->where('user_id', $request->user()->id);
        })->firstOrFail();

        return $this->send($request, $delivery);
    }

    public function riderSend(Request $request, int $deliveryId)
    {
        $delivery = Delivery::with('order.user', 'rider')
            ->where('rider_id', $request->user()->id)
            ->findOrFail($deliveryId);

        return $this->send($request, $delivery);
    }

    private function open(Delivery $delivery)
    {
        abort_if(!$delivery->rider || !$delivery->order, 404);
        $conversation = DeliveryChatConversation::firstOrCreate(
            ['delivery_id' => $delivery->id, 'rider_id' => $delivery->rider_id],
            ['customer_id' => $delivery->order->user_id]
        );
        // A delivery's customer is immutable, but re-check it in case legacy data was changed.
        abort_unless((int) $conversation->customer_id === (int) $delivery->order->user_id, 404);

        return $this->ok($this->payload($conversation, $delivery));
    }

    private function send(Request $request, Delivery $delivery)
    {
        abort_if(!$delivery->rider || !$delivery->order, 404);
        abort_unless(in_array($delivery->status, ['assigned', 'accepted', 'picked_up', 'on_the_way'], true), 409, 'Chat is closed because this delivery is no longer active.');
        $data = $request->validate(['body' => 'required|string|max:3000']);

        $message = DB::transaction(function () use ($request, $delivery, $data) {
            $conversation = DeliveryChatConversation::firstOrCreate(
                ['delivery_id' => $delivery->id, 'rider_id' => $delivery->rider_id],
                ['customer_id' => $delivery->order->user_id]
            );
            abort_unless((int) $conversation->customer_id === (int) $delivery->order->user_id, 404);
            $message = DeliveryChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $request->user()->id,
                'body' => trim($data['body']),
            ]);
            $conversation->update(['last_message_at' => now()]);
            return [$message, $conversation];
        });

        [$savedMessage, $conversation] = $message;
        $peerId = (int) $request->user()->id === (int) $delivery->rider_id
            ? (int) $delivery->order->user_id
            : (int) $delivery->rider_id;
        DB::afterCommit(fn () => LiveUpdate::dispatch('delivery_chat', $savedMessage->id, $peerId, [
            'conversation_id' => $conversation->id,
            'delivery_id' => $delivery->id,
        ]));

        return $this->ok($this->payload($conversation, $delivery->fresh(['order.user', 'rider'])), 'Message sent.', 201);
    }

    private function payload(DeliveryChatConversation $conversation, Delivery $delivery): array
    {
        $messages = $conversation->messages()->with('sender:id,name,avatar')->latest('id')->limit(100)->get()->sortBy('id')->values();
        $profile = fn ($user) => [
            'id' => $user?->id,
            'name' => $user?->name,
            'avatar_url' => $user?->avatar ? asset('storage/' . ltrim($user->avatar, '/')) : null,
        ];

        return [
            'conversation_id' => $conversation->id,
            'delivery_id' => $delivery->id,
            'order_id' => $delivery->order_id,
            'order_number' => $delivery->order?->order_number,
            'status' => $delivery->status,
            'customer' => $profile($delivery->order?->user),
            'rider' => $profile($delivery->rider),
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name,
                'sender_avatar_url' => $message->sender?->avatar ? asset('storage/' . ltrim($message->sender->avatar, '/')) : null,
                'body' => $message->body,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ])->all(),
        ];
    }
}