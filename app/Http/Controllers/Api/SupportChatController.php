<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\SupportAssistant;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    use Responds;

    public function current(Request $request)
    {
        $conversation = SupportConversation::where('user_id', $request->user()->id)
            ->where('status', '!=', 'closed')->latest('id')->first();

        return $this->ok($conversation ? $this->payload($conversation) : null);
    }

    public function start(Request $request)
    {
        $conversation = SupportConversation::firstOrCreate(
            ['user_id' => $request->user()->id, 'status' => 'open'],
            ['last_message_at' => now()]
        );

        return $this->ok($this->payload($conversation), 'Support conversation opened.');
    }

    public function startGuest(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'contact' => 'required|string|max:150',
        ]);

        $token = Str::random(48);
        $conversation = SupportConversation::create([
            'guest_uuid' => (string) Str::uuid(),
            'guest_token_hash' => hash('sha256', $token),
            'guest_name' => $data['name'],
            'guest_contact' => $data['contact'],
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        return $this->ok([
            'conversation' => $this->payload($conversation),
            'guest_token' => $token,
        ], 'Support conversation opened.', 201);
    }

    public function show(Request $request, SupportConversation $conversation)
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
        return $this->ok($this->payload($conversation));
    }

    public function showGuest(Request $request, string $uuid)
    {
        $conversation = $this->guestConversation($request, $uuid);
        return $this->ok($this->payload($conversation));
    }

    public function send(Request $request, SupportConversation $conversation, SupportAssistant $assistant)
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
        return $this->storeCustomerMessage($request, $conversation, $assistant, $request->user()->id);
    }

    public function sendGuest(Request $request, string $uuid, SupportAssistant $assistant)
    {
        $conversation = $this->guestConversation($request, $uuid);
        return $this->storeCustomerMessage($request, $conversation, $assistant, null);
    }

    public function attachment(Request $request, SupportConversation $conversation, SupportMessage $message)
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
        abort_unless((int) $message->conversation_id === (int) $conversation->id && $message->attachment_path, 404);
        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function guestAttachment(Request $request, string $uuid, SupportMessage $message)
    {
        $conversation = $this->guestConversation($request, $uuid);
        abort_unless((int) $message->conversation_id === (int) $conversation->id && $message->attachment_path, 404);
        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function storeCustomerMessage(Request $request, SupportConversation $conversation, SupportAssistant $assistant, ?int $userId)
    {
        abort_if($conversation->status === 'closed', 409, 'This conversation is closed. Start a new support chat.');

        $data = $request->validate([
            'body' => 'nullable|string|max:3000',
            'order_id' => 'nullable|integer|exists:orders,id',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,quicktime|max:20480',
        ]);
        if (blank($data['body'] ?? null) && !$request->hasFile('attachment')) {
            return $this->fail('Write a message or attach a photo/video.');
        }

        if (!empty($data['order_id'])) {
            $order = Order::findOrFail($data['order_id']);
            abort_unless($userId && (int) $order->user_id === $userId, 404);
            $conversation->refund_order_id = $order->id;
        }

        $attributes = [
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'sender_id' => $userId,
            'body' => $data['body'] ?? null,
        ];
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attributes['attachment_path'] = $file->store('support-attachments', 'local');
            $attributes['attachment_name'] = $file->getClientOriginalName();
            $attributes['attachment_mime'] = $file->getMimeType();
        }
        $message = SupportMessage::create($attributes);
        $conversation->last_message_at = now();

        if (!$conversation->human_replied_at) {
            $reply = $assistant->reply((string) ($data['body'] ?? ''));
            SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'assistant',
                'body' => $reply,
            ]);
            $conversation->status = 'waiting_admin';
        } else {
            $conversation->status = 'open';
        }
        $conversation->save();

        return $this->ok($this->payload($conversation->fresh()), 'Message sent.', 201);
    }

    private function guestConversation(Request $request, string $uuid): SupportConversation
    {
        $conversation = SupportConversation::where('guest_uuid', $uuid)->firstOrFail();
        $provided = (string) $request->header('X-Support-Token');
        abort_unless($provided !== '' && hash_equals($conversation->guest_token_hash ?? '', hash('sha256', $provided)), 404);
        return $conversation;
    }

    private function payload(SupportConversation $conversation): array
    {
        $conversation->load('messages');
        return [
            'id' => $conversation->id,
            'guest_uuid' => $conversation->guest_uuid,
            'status' => $conversation->status,
            'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
            'messages' => $conversation->messages->map(function ($message) use ($conversation) {
                $guestRoute = $conversation->guest_uuid;
                $attachmentUrl = null;
                if ($message->attachment_path) {
                    $attachmentUrl = $guestRoute
                        ? route('api.support.guest.attachment', ['uuid' => $guestRoute, 'message' => $message->id])
                        : route('api.support.attachment', ['conversation' => $conversation->id, 'message' => $message->id]);
                }
                return [
                    'id' => $message->id,
                    'sender_type' => $message->sender_type,
                    'body' => $message->body,
                    'attachment_url' => $attachmentUrl,
                    'attachment_mime' => $message->attachment_mime,
                    'attachment_name' => $message->attachment_name,
                    'created_at' => optional($message->created_at)->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }
}