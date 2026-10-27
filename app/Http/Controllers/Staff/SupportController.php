<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\Activity;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $conversations = SupportConversation::where('user_id', $request->user()->id)
            ->with(['messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->latest('last_message_at')
            ->paginate(10)
            ->withQueryString();

        $selected = null;
        if ($request->filled('conversation')) {
            $selected = SupportConversation::where('user_id', $request->user()->id)
                ->whereKey($request->integer('conversation'))
                ->first();
        }
        $selected ??= SupportConversation::where('user_id', $request->user()->id)
            ->latest('last_message_at')
            ->first();

        if ($selected) {
            $selected->setRelation('messages', $selected->messages()->with('sender')->orderBy('id')->get());
        }

        return view('staff.support', compact('conversations', 'selected'));
    }

    public function poll(Request $request, SupportConversation $conversation)
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
        $conversation->load(['messages.sender']);

        return response()->json([
            'html' => view('staff._support_thread', compact('conversation'))->render(),
            'status' => $conversation->status,
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate(['body' => 'required|string|max:3000']);
        $staff = $request->user();

        $conversation = DB::transaction(function () use ($staff, $data) {
            $conversation = SupportConversation::where('user_id', $staff->id)
                ->whereIn('status', ['open', 'waiting_admin'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $conversation ??= SupportConversation::create([
                'user_id' => $staff->id,
                'status' => 'open',
                'last_message_at' => now(),
            ]);

            SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'staff',
                'sender_id' => $staff->id,
                'body' => trim($data['body']),
            ]);
            $conversation->update(['status' => 'waiting_admin', 'last_message_at' => now()]);

            return $conversation->fresh();
        });

        User::where('role', 'admin')->where('status', 'active')->pluck('id')->each(
            fn ($adminId) => Notifier::send(
                (int) $adminId,
                'Staff support message',
                "{$staff->name} sent a support message.",
                'staff_support',
                ['conversation_id' => (string) $conversation->id],
            ),
        );
        Activity::log('message', 'staff_support', "Staff {$staff->id} sent a support message");

        return redirect()->route('staff.support', ['conversation' => $conversation->id])
            ->with('success', 'Your message was sent to the administrator.');
    }
}
