<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundAttempt;
use App\Models\RefundRequest;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RefundService
{
    public function availableCents(int $paymentId, ?int $excludeRequestId = null): int
    {
        $payment = Payment::find($paymentId);
        if (!$payment || !in_array($payment->status, ['paid', 'partially_refunded'], true)) return 0;
        $success = (int) RefundAttempt::whereHas('refundRequest', fn ($q) => $q->where('payment_id', $paymentId))->where('status', 'succeeded')->sum('amount_cents');
        $reserved = 0;
        RefundRequest::where('payment_id', $paymentId)
            ->whereIn('status', ['approved', 'processing', 'needs_review', 'partially_refunded'])
            ->when($excludeRequestId, fn ($q) => $q->where('id', '<>', $excludeRequestId))
            ->get(['id', 'approved_amount_cents'])
            ->each(function (RefundRequest $refund) use (&$reserved) {
                $succeeded = (int) $refund->attempts()->where('status', 'succeeded')->sum('amount_cents');
                $reserved += max(0, (int) ($refund->approved_amount_cents ?? 0) - $succeeded);
            });
        return max(0, (int) round((float) $payment->amount * 100) - $success - $reserved);
    }

    public function refundLatePayment(Order $order): void
    {
        $order->loadMissing('payments');
        $payment = $order->payments()->where('provider', 'paymongo')->where('method', 'qrph')->whereNotNull('provider_payment_id')->latest('id')->first();
        if (!$payment || !in_array($order->status, ['cancelled', 'rejected', 'expired'], true)) return;
        $refund = DB::transaction(function () use ($order, $payment) {
            $existing = RefundRequest::where('source', 'late_payment')->where('payment_id', $payment->id)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status === 'failed') $existing->update(['status' => 'approved']);
                return $existing;
            }
            return RefundRequest::create([
                'order_id' => $order->id, 'payment_id' => $payment->id, 'user_id' => $order->user_id,
                'reason' => 'order_issue', 'explanation' => 'Automatic recovery for a verified PayMongo payment received after the order became terminal.',
                'status' => 'approved', 'source' => 'late_payment',
                'requested_amount_cents' => (int) round((float) $payment->amount * 100),
                'approved_amount_cents' => (int) round((float) $payment->amount * 100),
            ]);
        });
        $this->process($refund);
    }

    public function applyWebhookResource(array $resource): void
    {
        $attrs = (array) data_get($resource, 'attributes', []);
        $paymentId = data_get($attrs, 'payment_id');
        $refundId = data_get($resource, 'id');
        $attempt = $refundId ? RefundAttempt::where('provider_refund_id', $refundId)->first() : null;
        if (!$attempt && $paymentId) {
            $notes = (string) data_get($attrs, 'notes', '');
            if (preg_match('/KJM refund request (\d+) attempt (\d+)/', $notes, $matches)) {
                $attempt = RefundAttempt::whereKey((int) $matches[2])->where('refund_request_id', (int) $matches[1])->first();
            }
        }
        if (!$attempt) return;
        $attempt->load('refundRequest.payment');
        $this->applyProviderResource($attempt, $resource, $attempt->refundRequest->payment);
    }

    public function notifyRequestCreated(RefundRequest $refund): void
    {
        $refund->loadMissing(['order', 'user']);
        $title = 'Refund request received';
        $message = 'Refund request for order '.($refund->order?->order_number ?? '#'.$refund->order_id).' is awaiting review.';
        Notifier::send((int) $refund->user_id, $title, $message, 'refund', ['refund_request_id' => (string) $refund->id, 'order_id' => (string) $refund->order_id]);
        $this->notifyAdmins($refund, 'New refund request', $message);
    }

    public function process(RefundRequest $refund, ?int $actorId = null): RefundRequest
    {
        $refund = $refund->fresh();
        abort_unless($refund && in_array($refund->source, ['customer', 'late_payment'], true), 404);
        if ($refund->status === 'refunded') return $refund->load(['attempts', 'order', 'payment', 'user']);
        abort_unless(in_array($refund->status, ['approved', 'processing', 'needs_review', 'partially_refunded', 'failed'], true), 409, 'This refund request is not approved for processing.');

        $attempt = DB::transaction(function () use ($refund, $actorId) {
            $lockedRequest = RefundRequest::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::whereKey($lockedRequest->order_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($lockedRequest->payment_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $lockedPayment->order_id === (int) $lockedRequest->order_id
                && (int) $lockedOrder->user_id === (int) $lockedRequest->user_id,
                422, 'Refund request order, owner, and payment references do not match.');
            abort_unless(in_array($lockedPayment->status, ['paid', 'partially_refunded'], true)
                && in_array($lockedOrder->payment_status, ['paid', 'partially_refunded'], true)
                && $lockedPayment->provider === 'paymongo' && $lockedPayment->method === 'qrph' && $lockedPayment->provider_payment_id,
                422, 'A currently paid, verified PayMongo QRPh payment is required.');
            $active = RefundAttempt::where('refund_request_id', $lockedRequest->id)
                ->whereIn('status', ['pending', 'processing', 'ambiguous'])->latest('id')->first();
            if ($active) return $active;

            $approved = (int) ($lockedRequest->approved_amount_cents ?? 0);
            $requestRefunded = (int) RefundAttempt::where('refund_request_id', $lockedRequest->id)->where('status', 'succeeded')->sum('amount_cents');
            $amount = min($approved - $requestRefunded, $this->availableCents($lockedPayment->id, $lockedRequest->id));
            abort_if($amount < 100, 422, 'No refundable amount remains for this approved request.');

            return RefundAttempt::create([
                'refund_request_id' => $lockedRequest->id,
                'amount_cents' => $amount,
                'status' => 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'idempotency_key_created_at' => now(),
                'created_by' => $actorId,
            ]);
        });

        if (in_array($attempt->status, ['pending', 'processing', 'ambiguous'], true)) {
            $this->reconcileAttempt($attempt->fresh(), true);
            return $refund->fresh(['attempts', 'order', 'payment', 'user']);
        }
        return $refund->fresh(['attempts', 'order', 'payment', 'user']);
    }

    public function reconcile(RefundRequest $refund): RefundRequest
    {
        $attempt = $refund->attempts()->whereIn('status', ['pending', 'processing', 'ambiguous'])->latest('id')->first();
        if ($attempt) $this->reconcileAttempt($attempt, false);
        return $refund->fresh(['attempts', 'order', 'payment', 'user']);
    }

    private function reconcileAttempt(RefundAttempt $attempt, bool $allowIdempotentPost): RefundAttempt
    {
        $attempt->load('refundRequest.payment');
        $payment = $attempt->refundRequest->payment;
        if (!$payment?->provider_payment_id) return $attempt;

        if ($attempt->provider_refund_id) {
            try {
                $response = $this->request()->get('/refunds/'.rawurlencode($attempt->provider_refund_id));
            } catch (\Throwable $exception) {
                return $this->ambiguous($attempt, 'retrieve_outcome_uncertain');
            }
            if (!$response->successful()) return $this->ambiguous($attempt, 'retrieve_failed');
            $resource = $response->json('data');
            return $this->applyProviderResource($attempt, $resource, $payment);
        }

        // With no refund id, the only safe create retry is the same payload/key within PayMongo's 24h window.
        $keyStillValid = $attempt->idempotency_key_created_at && $attempt->idempotency_key_created_at->gt(now()->subHours(24));
        if ($allowIdempotentPost && $keyStillValid) return $this->submitSameAttempt($attempt, $payment);

        // Past the idempotency window, search provider history. Never issue a new POST if a prior outcome is uncertain.
        $marker = $this->attemptMarker($attempt);
        $after = null;
        for ($page = 0; $page < 100; $page++) {
            $params = ['data.attributes.payment_id' => $payment->provider_payment_id, 'data.attributes.limit' => 100];
            if ($after) $params['data.attributes.after'] = $after;
            try {
                $response = $this->request()->get('/refunds', $params);
            } catch (\Throwable $exception) {
                return $this->ambiguous($attempt, 'list_outcome_uncertain');
            }
            if (!$response->successful()) return $this->ambiguous($attempt, 'list_failed');
            $items = (array) $response->json('data', []);
            foreach ($items as $resource) {
                $attrs = (array) data_get($resource, 'attributes', []);
                if ((string) data_get($attrs, 'payment_id') === (string) $payment->provider_payment_id
                    && (int) data_get($attrs, 'amount') === (int) $attempt->amount_cents
                    && str_contains((string) data_get($attrs, 'notes', ''), $marker)) {
                    return $this->applyProviderResource($attempt, $resource, $payment);
                }
            }
            if (count($items) < 100) break;
            $lastId = data_get(end($items), 'id');
            if (!$lastId || $lastId === $after) return $this->ambiguous($attempt, 'list_cursor_unresolved');
            $after = $lastId;
        }
        return $this->ambiguous($attempt, 'provider_outcome_unresolved');
    }

    private function submitSameAttempt(RefundAttempt $attempt, Payment $payment): RefundAttempt
    {
        try {
            $response = $this->request()->withHeaders(['Idempotency-Key' => $attempt->idempotency_key])->post('/refunds', ['data' => ['attributes' => [
                'amount' => (int) $attempt->amount_cents,
                'payment_id' => (string) $payment->provider_payment_id,
                'reason' => 'requested_by_customer',
                'notes' => $this->attemptMarker($attempt),
            ]]]);
        } catch (\Throwable $exception) {
            return $this->ambiguous($attempt, 'provider_timeout_or_connection_failure');
        }
        if (!$response->successful()) {
            // Provider 4xx is a definitive rejection; transport/server errors remain ambiguous.
            if ($response->status() >= 400 && $response->status() < 500 && !in_array($response->status(), [408, 409, 429], true)) {
                $attempt->update(['status' => 'failed', 'provider_status' => 'failed', 'error_code' => 'provider_rejected', 'error_message' => 'PayMongo rejected the refund request.']);
                $this->syncRequestAndPayment($attempt->fresh('refundRequest.payment'));
                return $attempt->fresh();
            }
            return $this->ambiguous($attempt, 'provider_response_uncertain');
        }
        return $this->applyProviderResource($attempt, $response->json('data'), $payment);
    }

    private function applyProviderResource(RefundAttempt $attempt, mixed $resource, Payment $payment): RefundAttempt
    {
        $id = data_get($resource, 'id');
        $attrs = (array) data_get($resource, 'attributes', []);
        $status = (string) data_get($attrs, 'status', '');
        $currency = strtoupper((string) data_get($attrs, 'currency', 'PHP'));
        $providerUpdatedAt = $this->providerTimestamp(data_get($attrs, 'updated_at'));
        if (!$id || (string) data_get($attrs, 'payment_id') !== (string) $payment->provider_payment_id
            || (int) data_get($attrs, 'amount') !== (int) $attempt->amount_cents || $currency !== 'PHP'
            || !in_array($status, ['pending', 'processing', 'succeeded', 'failed'], true)) {
            return $this->ambiguous($attempt, 'provider_resource_mismatch');
        }

        return DB::transaction(function () use ($attempt, $id, $attrs, $status, $providerUpdatedAt) {
            $lockedAttempt = RefundAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if (!$lockedAttempt) return $attempt->fresh();

            // A successful refund is terminal for accounting purposes. Delayed webhooks
            // must never lower the succeeded total or restore the payment to paid.
            if ($lockedAttempt->status === 'succeeded' && $status !== 'succeeded') return $lockedAttempt;

            $currentProviderUpdatedAt = $lockedAttempt->provider_updated_at;
            if ($currentProviderUpdatedAt && $providerUpdatedAt && $providerUpdatedAt->lt($currentProviderUpdatedAt)) return $lockedAttempt;

            // If timestamps cannot establish order (missing/equal), do not accept a
            // lower provider state. A newer provider timestamp may legitimately advance it.
            if (!$currentProviderUpdatedAt || !$providerUpdatedAt || $providerUpdatedAt->equalTo($currentProviderUpdatedAt)) {
                $rank = ['pending' => 0, 'processing' => 1, 'failed' => 2, 'succeeded' => 3];
                $incomingRank = $rank[$status] ?? -1;
                $currentRank = $rank[$lockedAttempt->provider_status ?? $lockedAttempt->status] ?? -1;
                if ($incomingRank < $currentRank) return $lockedAttempt;
            }

            $lockedAttempt->update([
                'provider_refund_id' => $id,
                'status' => $status,
                'provider_status' => $status,
                'last_checked_at' => now(),
                'provider_created_at' => $this->providerTimestamp(data_get($attrs, 'created_at')),
                'provider_updated_at' => $providerUpdatedAt ?? $currentProviderUpdatedAt,
                'error_code' => null,
                'error_message' => null,
            ]);
            $this->syncRequestAndPayment($lockedAttempt->fresh('refundRequest.payment'));
            return $lockedAttempt->fresh();
        });
    }

    private function syncRequestAndPayment(RefundAttempt $attempt): void
    {
        $refund = $attempt->refundRequest;
        $payment = $refund->payment;
        $successForRequest = (int) $refund->attempts()->where('status', 'succeeded')->sum('amount_cents');
        $successForPayment = (int) RefundAttempt::whereHas('refundRequest', fn ($q) => $q->where('payment_id', $payment->id))->where('status', 'succeeded')->sum('amount_cents');
        $fullAmount = (int) round((float) $payment->amount * 100);
        $nextRequestStatus = $successForRequest >= (int) $refund->approved_amount_cents ? 'refunded'
            : ($successForRequest > 0 ? 'partially_refunded'
                : (in_array($attempt->status, ['pending', 'processing'], true) ? 'processing' : ($attempt->status === 'ambiguous' ? 'needs_review' : ($attempt->status === 'failed' ? 'failed' : $refund->status))));
        $oldStatus = $refund->status;
        $refund->update(['refunded_amount_cents' => $successForRequest, 'status' => $nextRequestStatus]);
        $payment->update(['status' => $successForPayment >= $fullAmount ? 'refunded' : ($successForPayment > 0 ? 'partially_refunded' : 'paid')]);
        // Payment status is distinct from order fulfillment status; never change cancelled/terminal order status.
        $payment->order()->update(['payment_status' => $payment->fresh()->status]);

        if ($oldStatus !== $nextRequestStatus) {
            $title = match ($nextRequestStatus) {
                'refunded', 'partially_refunded' => 'Refund status updated',
                'processing' => 'Refund processing',
                'failed' => 'Refund processing failed',
                default => 'Refund status updated',
            };
            $message = match ($nextRequestStatus) {
                'refunded' => 'PayMongo confirmed your refund.',
                'partially_refunded' => 'PayMongo confirmed a partial refund.',
                'processing' => 'Your approved refund is being processed.',
                'failed' => 'PayMongo could not complete this refund. The request is available for review.',
                default => 'Your refund request has been updated.',
            };
            Notifier::send((int) $refund->user_id, $title, $message, 'refund', ['refund_request_id' => (string) $refund->id, 'order_id' => (string) $refund->order_id, 'status' => $nextRequestStatus]);
            $this->notifyAdmins($refund, $title, $message);
        }
    }

    public function notifyAdmins(RefundRequest $refund, string $title, string $message, ?int $exceptUserId = null): void
    {
        foreach (User::where('role', 'admin')->where('status', 'active')->when($exceptUserId, fn ($q) => $q->where('id', '<>', $exceptUserId))->pluck('id') as $adminId) {
            Notifier::send((int) $adminId, $title, $message, 'refund', ['refund_request_id' => (string) $refund->id, 'order_id' => (string) $refund->order_id, 'status' => (string) $refund->status]);
        }
    }

    private function ambiguous(RefundAttempt $attempt, string $code): RefundAttempt
    {
        return DB::transaction(function () use ($attempt, $code) {
            $lockedAttempt = RefundAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if (!$lockedAttempt || $lockedAttempt->status === 'succeeded') return $lockedAttempt ?? $attempt->fresh();

            $lockedAttempt->update([
                'status' => 'ambiguous',
                'last_checked_at' => now(),
                'error_code' => $code,
                'error_message' => 'Provider outcome needs reconciliation before any new attempt.',
            ]);
            $this->syncRequestAndPayment($lockedAttempt->fresh('refundRequest.payment'));
            return $lockedAttempt->fresh();
        });
    }

    private function request()
    {
        return Http::withBasicAuth((string) config('paymongo.secret'), '')->acceptJson()->asJson()->timeout(20)->baseUrl(rtrim((string) config('paymongo.base_url'), '/'));
    }

    private function attemptMarker(RefundAttempt $attempt): string
    {
        return 'KJM refund request '.$attempt->refund_request_id.' attempt '.$attempt->id;
    }

    private function providerTimestamp(mixed $value): mixed
    {
        return is_numeric($value) ? \Illuminate\Support\Carbon::createFromTimestampUTC((int) $value) : null;
    }
}
