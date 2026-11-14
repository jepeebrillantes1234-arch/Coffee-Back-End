<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RefundAttempt;
use App\Models\RefundRequest;
use App\Support\Activity;
use App\Support\Notifier;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RefundManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = RefundRequest::with(['order.user', 'payment', 'reviewer'])->latest('id');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to'));
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(fn ($q) => $q->where('reason', 'like', $term)->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)))
                ->orWhereHas('payment', fn ($p) => $p->where('provider_payment_id', 'like', $term)));
        }
        $refunds = $query->paginate(20)->withQueryString();
        return view('admin.refunds.index', compact('refunds'));
    }

    public function show(RefundRequest $refundRequest)
    {
        $refundRequest->load(['order.user', 'order.items.product', 'payment', 'reviewer', 'attempts']);
        return view('admin.refunds.show', compact('refundRequest'));
    }

    public function approve(Request $request, RefundRequest $refundRequest, RefundService $refunds)
    {
        $data = $request->validate([
            'approved_amount_php' => 'nullable|required_without:approved_amount_cents|numeric|min:1|decimal:0,2',
            'approved_amount_cents' => 'nullable|required_without:approved_amount_php|integer|min:100',
            'reason' => 'required|string|min:5|max:500',
        ]);
        if (array_key_exists('approved_amount_php', $data) && $data['approved_amount_php'] !== null) {
            $parts = explode('.', (string) $data['approved_amount_php'], 2);
            $approvedAmountCents = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        } else {
            // Preserve the previous centavo input for existing internal callers.
            $approvedAmountCents = (int) $data['approved_amount_cents'];
        }
        DB::transaction(function () use ($request, $refundRequest, $data, $refunds, $approvedAmountCents) {
            $refund = RefundRequest::whereKey($refundRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($refund->status === 'pending', 409, 'Only pending requests can be approved.');
            $order = \App\Models\Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $payment->order_id === (int) $refund->order_id
                && (int) $order->user_id === (int) $refund->user_id,
                422, 'Refund request order, owner, and payment references do not match.');
            abort_unless($payment->provider === 'paymongo' && $payment->method === 'qrph'
                && $payment->provider_payment_id && in_array($payment->status, ['paid', 'partially_refunded'], true),
                422, 'Only a verified paid PayMongo QRPh transaction can be approved.');
            abort_if($approvedAmountCents > $refunds->availableCents($payment->id, $refund->id), 422, 'Approved amount exceeds the remaining unreserved paid amount.');
            $refund->update([
                'status' => 'approved',
                'approved_amount_cents' => $approvedAmountCents,
                'decision_reason' => trim($data['reason']),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });
        Activity::log('approve', 'refunds', "Approved refund request #{$refundRequest->id} for {$approvedAmountCents} centavos.");
        Notifier::send((int) $refundRequest->user_id, 'Refund request approved', 'Your refund request was approved and is awaiting processing.', 'refund', ['refund_request_id' => (string) $refundRequest->id, 'order_id' => (string) $refundRequest->order_id, 'status' => 'approved']);
        $refunds->notifyAdmins($refundRequest->fresh(), 'Refund request approved', "Refund request #{$refundRequest->id} was approved.", (int) $request->user()->id);
        return back()->with('success', 'Refund request approved. PayMongo processing is a separate step.');
    }

    public function reject(Request $request, RefundRequest $refundRequest)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($request, $refundRequest, $data) {
            $refund = RefundRequest::whereKey($refundRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($refund->status === 'pending', 409, 'Only pending requests can be rejected.');
            $refund->update(['status' => 'rejected', 'decision_reason' => trim($data['reason']), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });
        Activity::log('reject', 'refunds', "Rejected refund request #{$refundRequest->id}: {$data['reason']}");
        Notifier::send((int) $refundRequest->user_id, 'Refund request update', 'Your refund request was declined. Review the reason in Refund History.', 'refund', ['refund_request_id' => (string) $refundRequest->id, 'order_id' => (string) $refundRequest->order_id, 'status' => 'rejected']);
        app(RefundService::class)->notifyAdmins($refundRequest->fresh(), 'Refund request rejected', "Refund request #{$refundRequest->id} was rejected.", (int) $request->user()->id);
        return back()->with('success', 'Refund request rejected with reason recorded.');
    }

    public function process(Request $request, RefundRequest $refundRequest, RefundService $refunds)
    {
        $result = $refunds->process($refundRequest, (int) $request->user()->id);
        Activity::log('process', 'refunds', "Processed/reconciled PayMongo refund request #{$refundRequest->id}.");
        return redirect()->route('admin.refunds.show', $result)->with('success', 'Provider refund status checked. See the attempt history for the confirmed status.');
    }

    public function reconcile(RefundRequest $refundRequest, RefundService $refunds)
    {
        $result = $refunds->reconcile($refundRequest);
        Activity::log('reconcile', 'refunds', "Reconciled PayMongo refund request #{$refundRequest->id}.");
        $message = $result->attempts->isEmpty()
            ? 'No PayMongo refund attempt exists yet. Use Process / Retry safely to submit the approved refund.'
            : 'Provider refund status checked.';

        return redirect()->route('admin.refunds.show', $result)->with('success', $message);
    }

    public function attachment(RefundRequest $refundRequest, int $index)
    {
        $attachment = ($refundRequest->attachments ?? [])[$index] ?? null;
        abort_unless($attachment && Storage::disk('local')->exists($attachment['path'] ?? ''), 404);
        return Storage::disk('local')->response($attachment['path'], $attachment['name'] ?? 'refund-evidence', [
            'Content-Type' => $attachment['mime'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
