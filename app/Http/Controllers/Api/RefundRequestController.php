<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundAttempt;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RefundRequestController extends Controller
{
    private const ACTIVE_STATUSES = ['pending', 'approved', 'processing', 'needs_review'];

    public function index(Request $request)
    {
        $rows = RefundRequest::with(['order:id,order_number,status,total,payment_method', 'attempts'])
            ->where('user_id', $request->user()->id)
            ->latest('id')->limit(100)->get()
            ->map(fn (RefundRequest $refund) => $this->payload($refund));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function forOrder(Request $request, int $orderId)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($orderId);
        $rows = RefundRequest::with(['order:id,order_number,status,total,payment_method', 'attempts'])
            ->where('user_id', $request->user()->id)
            ->where('order_id', $order->id)
            ->latest('id')->get()
            ->map(fn (RefundRequest $refund) => $this->payload($refund));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request, int $orderId, RefundService $refunds)
    {
        $data = $request->validate([
            'reason' => 'required|string|in:quality_issue,missing_or_incorrect_items,duplicate_payment,order_issue,other',
            'explanation' => 'required|string|min:5|max:1000',
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $storedFiles = [];
        try {
            [$refund, $created] = DB::transaction(function () use ($request, $orderId, $data, $refunds, &$storedFiles) {
                $order = Order::whereKey($orderId)
                    ->where('user_id', $request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = RefundRequest::with('attempts')
                    ->where('order_id', $order->id)
                    ->whereIn('status', self::ACTIVE_STATUSES)
                    ->latest('id')->first();
                if ($existing) return [$existing, false];

                abort_unless($order->payment_method === 'qrph'
                    && in_array($order->payment_status, ['paid', 'partially_refunded'], true), 422,
                    'Only paid QRPh orders can be submitted for refund review. Unpaid orders must be cancelled instead.');

                $payment = Payment::where('order_id', $order->id)
                    ->where('method', 'qrph')
                    ->whereIn('status', ['paid', 'partially_refunded'])
                    ->latest('id')->lockForUpdate()->first();
                abort_unless($payment && $payment->provider === 'paymongo' && $payment->provider_payment_id, 422,
                    'A verified PayMongo payment is required for a refund request.');

                $remaining = $refunds->availableCents($payment->id);
                abort_if($remaining < 100, 422, 'This payment has no refundable amount remaining.');

                $refund = RefundRequest::create([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'user_id' => $request->user()->id,
                    'reason' => $data['reason'],
                    'explanation' => trim($data['explanation']),
                    'status' => 'pending',
                    'source' => 'customer',
                    'requested_amount_cents' => $remaining,
                    'refunded_amount_cents' => 0,
                ]);

                $attachments = [];
                foreach ($request->file('photos', []) as $photo) {
                    $path = $photo->store('refund-attachments', 'local');
                    $storedFiles[] = $path;
                    $attachments[] = [
                        'path' => $path,
                        'name' => $photo->getClientOriginalName(),
                        'mime' => $photo->getMimeType(),
                    ];
                }
                if ($attachments) $refund->update(['attachments' => $attachments]);

                return [$refund->load(['order', 'payment', 'attempts']), true];
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }

        $refund->loadMissing(['order', 'payment', 'attempts']);
        if ($created) $refunds->notifyRequestCreated($refund);

        return response()->json([
            'success' => true,
            'data' => $this->payload($refund),
            'already_exists' => !$created,
        ], $created ? 201 : 200);
    }

    public function attachment(Request $request, RefundRequest $refundRequest, int $index)
    {
        abort_unless((int) $refundRequest->user_id === (int) $request->user()->id, 404);
        return $this->sendAttachment($refundRequest, $index);
    }

    public function payload(RefundRequest $refund): array
    {
        return [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'order_number' => $refund->order?->order_number,
            'reason' => $refund->reason,
            'explanation' => $refund->explanation,
            'status' => $refund->status,
            'source' => $refund->source,
            'requested_amount' => $refund->requested_amount_cents,
            'approved_amount' => $refund->approved_amount_cents,
            'refunded_amount' => $refund->refunded_amount_cents,
            'decision_reason' => $refund->decision_reason,
            'reviewed_at' => $refund->reviewed_at?->toIso8601String(),
            'created_at' => $refund->created_at?->toIso8601String(),
            'attachments' => collect($refund->attachments ?? [])->map(fn ($attachment, $index) => [
                'name' => $attachment['name'] ?? 'Evidence photo',
                'url' => route('api.refunds.attachment', ['refundRequest' => $refund->id, 'index' => $index]),
            ])->values(),
            'attempts' => $refund->attempts->map(fn (RefundAttempt $attempt) => [
                'id' => $attempt->id,
                'provider_refund_id' => $attempt->provider_refund_id,
                'amount_cents' => $attempt->amount_cents,
                'status' => $attempt->status,
                'provider_status' => $attempt->provider_status,
                'created_at' => $attempt->created_at?->toIso8601String(),
                'updated_at' => $attempt->updated_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function sendAttachment(RefundRequest $refund, int $index)
    {
        $attachment = ($refund->attachments ?? [])[$index] ?? null;
        abort_unless($attachment && !empty($attachment['path']), 404);
        abort_unless(Storage::disk('local')->exists($attachment['path']), 404);

        return Storage::disk('local')->response($attachment['path'], $attachment['name'] ?? 'refund-evidence', [
            'Content-Type' => $attachment['mime'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
