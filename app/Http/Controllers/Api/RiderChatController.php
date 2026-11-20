<?php

namespace App\Http\Controllers\Api;

use App\Events\LiveUpdate;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryChatConversation;
use App\Models\DeliveryChatMessage;
use App\Models\Order;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    public function conversationAttachment(Request $request, int $deliveryId, int $messageId)
    {
        $delivery = Delivery::whereKey($deliveryId)
            ->where(function ($query) use ($request) {
                $query->where('rider_id', $request->user()->id)
                    ->orWhereHas('order', fn ($order) => $order->where('user_id', $request->user()->id));
            })->firstOrFail();
        $message = DeliveryChatMessage::where('id', $messageId)
            ->whereHas('conversation', fn ($query) => $query->where('delivery_id', $delivery->id))
            ->whereNotNull('attachment_path')->firstOrFail();

        return $this->attachmentResponse($message);
    }

    private function attachmentResponse(DeliveryChatMessage $message)
    {
        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
        $data = $request->validate([
            'body' => 'nullable|string|max:3000',
            'client_message_id' => 'nullable|uuid',
            'attachment' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        abort_if(blank($data['body'] ?? null) && !$request->hasFile('attachment'), 422, 'Write a message or attach a photo.');
        $clientMessageId = $data['client_message_id'] ?? (string) Str::uuid();

        $attachmentPath = null;
        try {
        $message = DB::transaction(function () use ($request, $delivery, $data, $clientMessageId, &$attachmentPath) {
            $conversation = DeliveryChatConversation::firstOrCreate(
                ['delivery_id' => $delivery->id, 'rider_id' => $delivery->rider_id],
                ['customer_id' => $delivery->order->user_id]
            );
            $conversation = DeliveryChatConversation::whereKey($conversation->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless((int) $conversation->customer_id === (int) $delivery->order->user_id, 404);
            $existing = DeliveryChatMessage::where('conversation_id', $conversation->id)
                ->where('client_message_id', $clientMessageId)->first();
            if ($existing) {
                abort_unless((int) $existing->sender_id === (int) $request->user()->id, 409, 'Message identifier was already used.');
                return [$existing, $conversation, false];
            }
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('delivery-chat-attachments', 'local');
            }
            $message = DeliveryChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $request->user()->id,
                'client_message_id' => $clientMessageId,
                'body' => trim((string) ($data['body'] ?? '')) ?: null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentPath ? $request->file('attachment')->getClientOriginalName() : null,
                'attachment_mime' => $attachmentPath ? $request->file('attachment')->getMimeType() : null,
            ]);
            $conversation->update(['last_message_at' => now()]);
            return [$message, $conversation, true];
        });
        } catch (\Throwable $exception) {
            if ($attachmentPath) Storage::disk('local')->delete($attachmentPath);
            throw $exception;
        }

        [$savedMessage, $conversation, $created] = $message;
        if ($created && (int) $request->user()->id === (int) $delivery->rider_id) {
            Notifier::riderChat($delivery->fresh(['order']), $savedMessage->load('conversation'));
        }

        $peerId = (int) $request->user()->id === (int) $delivery->rider_id
            ? (int) $delivery->order->user_id
            : (int) $delivery->rider_id;
        if ($created) DB::afterCommit(fn () => LiveUpdate::dispatch('delivery_chat', $savedMessage->id, $peerId, [
            'conversation_id' => $conversation->id,
            'delivery_id' => $delivery->id,
        ]));

        $savedMessage->load(['sender:id,name,avatar', 'conversation.delivery']);
        return $this->ok([
            'conversation_id' => $conversation->id,
            'message' => $this->messagePayload($savedMessage),
        ], 'Message sent.', $created ? 201 : 200);
    }

    private function payload(DeliveryChatConversation $conversation, Delivery $delivery): array
    {
        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar', 'conversation.delivery'])
            ->latest('id')->limit(100)->get()->sortBy('id')->values();
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
            'messages' => $messages->map(fn ($message) => $this->messagePayload($message))->all(),
        ];
    }

    private function messagePayload(DeliveryChatMessage $message): array
    {
        $conversation = $message->conversation;
        $delivery = $conversation?->delivery;
        $attachmentUrl = $message->attachment_path && $delivery
            ? route('api.delivery-chat.attachment', ['deliveryId' => $delivery->id, 'messageId' => $message->id])
            : null;

        return [
            'id' => $message->id,
            'client_message_id' => $message->client_message_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'sender_avatar_url' => $message->sender?->avatar ? asset('storage/' . ltrim($message->sender->avatar, '/')) : null,
            'body' => $message->body,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $message->attachment_mime,
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }
}
