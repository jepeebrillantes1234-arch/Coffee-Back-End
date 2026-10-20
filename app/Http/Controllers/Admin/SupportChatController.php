<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\PaymentGateway;
use App\Support\Activity;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportChatController extends Controller
{
    public function index()
    {
        $conversations = SupportConversation::with(['user:id,name,email,phone', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->latest('last_message_at')->paginate(20);
        return view('admin.support.index', compact('conversations'));
    }


    public function pollInbox(Request $request)
    {
        $conversations = SupportConversation::with(['user:id,name,email,phone', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->latest('last_message_at')->paginate(20)->withQueryString();

        return response()->json([
            'html' => view('admin.support._inbox', compact('conversations'))->render(),
        ]);
    }

    public function notifications()
    {
        $unanswered = SupportConversation::query()
            ->whereRaw('(select max(id) from support_messages where conversation_id = support_conversations.id and sender_type = ?) > coalesce((select max(id) from support_messages where conversation_id = support_conversations.id and sender_type = ?), 0)', ['customer', 'admin']);

        $count = (clone $unanswered)->count();
        $latest = (clone $unanswered)
            ->with(['user:id,name', 'messages' => fn ($q) => $q->where('sender_type', 'customer')->latest('id')->limit(1)])
            ->latest('last_message_at')->first();
        $message = $latest?->messages->first();

        return response()->json([
            'unread_count' => $count,
            'latest' => $latest && $message ? [
                'message_id' => $message->id,
                'conversation_id' => $latest->id,
                'customer' => $latest->user->name ?? $latest->guest_name ?? 'Customer',
                'preview' => $message->body ?: ($message->attachment_name ? 'Sent an attachment' : 'New message'),
                'url' => route('admin.support.show', $latest),
            ] : null,
        ]);
    }

    public function destroy(SupportConversation $conversation)
    {
        $conversation->load('messages');
        foreach ($conversation->messages as $message) {
            if ($message->attachment_path) {
                Storage::disk('local')->delete($message->attachment_path);
            }
        }

        $conversation->delete();
        Activity::log('delete', 'support_chat', 'Admin deleted a customer support conversation');

        return redirect()->route('admin.support.index')->with('success', 'Conversation and its attachments were deleted.');
    }

    public function pollConversation(SupportConversation $conversation)
    {
        $conversation->load(['user', 'refundOrder', 'messages' => fn ($q) => $q->orderBy('id')]);
        return response()->json([
            'html' => view('admin.support._messages', compact('conversation'))->render(),
            'status' => $conversation->status,
        ]);
    }
    public function show(SupportConversation $conversation)
    {
        $conversation->load(['user', 'refundOrder', 'messages' => fn ($q) => $q->orderBy('id')]);
        $orders = $conversation->user_id
            ? Order::where('user_id', $conversation->user_id)->latest('id')->limit(30)->get(['id', 'order_number', 'status', 'payment_status', 'total'])
            : collect();
        return view('admin.support.show', compact('conversation', 'orders'));
    }

    public function reply(Request $request, SupportConversation $conversation)
    {
        $data = $request->validate(['body' => 'required|string|max:3000']);
        abort_if($conversation->status === 'closed', 409, 'This conversation is closed.');

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $conversation->update(['human_replied_at' => now(), 'last_message_at' => now(), 'status' => 'open']);

        if ($conversation->user_id) {
            Notifier::send($conversation->user_id, 'Customer service replied', 'You have a new message from KJM Coffee Shop Customer Service.', 'support_chat', [
                'conversation_id' => (string) $conversation->id,
            ]);
        }
        Activity::log('reply', 'support_chat', 'Admin replied to a customer support conversation');

        return back()->with('success', 'Reply sent to the customer.');
    }

    public function linkOrder(Request $request, SupportConversation $conversation)
    {
        $data = $request->validate(['order_id' => 'required|integer|exists:orders,id']);
        $order = Order::findOrFail($data['order_id']);
        abort_unless($conversation->user_id && (int) $order->user_id === (int) $conversation->user_id, 404);
        $conversation->update(['refund_order_id' => $order->id]);
        return back()->with('success', 'Order linked to this support conversation.');
    }

    public function refund(Request $request, SupportConversation $conversation, PaymentGateway $gateway)
    {
        $order = $conversation->refundOrder;
        abort_unless($order && $conversation->user_id && (int) $order->user_id === (int) $conversation->user_id, 404);
        if ($order->payment_status !== 'paid') {
            return back()->with('error', 'Only a paid order can be refunded. Please review its payment status.');
        }

        try {
            $gateway->refundOrder($order);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'The refund could not be processed. Check the payment record and try again.');
        }

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'sender_id' => $request->user()->id,
            'body' => 'The refund for order '.$order->order_number.' was processed. Please check your original payment method.',
        ]);
        $conversation->update(['human_replied_at' => now(), 'last_message_at' => now(), 'status' => 'open']);
        Activity::log('refund', 'support_chat', 'Admin processed a customer refund from support chat');

        return back()->with('success', 'Refund processed and customer notified.');
    }

    public function close(SupportConversation $conversation)
    {
        $conversation->update(['status' => 'closed', 'last_message_at' => now()]);
        return redirect()->route('admin.support.index')->with('success', 'Conversation closed.');
    }

    public function attachment(SupportConversation $conversation, SupportMessage $message)
    {
        abort_unless((int) $message->conversation_id === (int) $conversation->id && $message->attachment_path, 404);
        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}