<?php
namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\Order;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LoyaltyService
{
    public function startMembershipCheckout(User $user): array
    {
        [$membership, $payment] = DB::transaction(function () use ($user) {
            $membership = Membership::firstOrCreate(['user_id' => $user->id], [
                'member_id' => 'KJM-' . Str::upper(Str::random(10)),
                'status' => 'pending',
            ]);
            $membership = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            if ($membership->isActive()) throw new RuntimeException('Your membership is already active.');
            if ($membership->status === 'inactive' && $membership->payments()->where('status', 'paid')->exists()) {
                throw new RuntimeException('This membership was deactivated. Contact customer service for assistance.');
            }

            $payment = $membership->payments()->where('status', 'pending')->latest('id')->lockForUpdate()->first();
            if (!$payment) {
                $payment = $membership->payments()->create([
                    'provider' => config('paymongo.mode') === 'simulated' ? 'simulated' : 'paymongo',
                    'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
                    'amount' => 100,
                    'status' => 'pending',
                ]);
            }
            if ($payment->provider === 'simulated' && !$payment->checkout_url) {
                $session = 'sim_mem_' . Str::random(24);
                $base = rtrim((string) config('paymongo.public_url'), '/');
                $payment->update([
                    'provider_session_id' => $session,
                    'checkout_url' => $base . '/pay/simulate-membership/' . $session,
                ]);
                $payment->refresh();
            }
            return [$membership, $payment];
        });
        if ($payment->checkout_url) return $this->checkoutPayload($membership, $payment);

        $base = rtrim((string) config('paymongo.public_url'), '/');
        $key = config('paymongo.secret');
        if (!$key) throw new RuntimeException('Online membership payment is not configured.');
        // Reuse the same payment reference as the provider idempotency key. If a
        // network timeout occurs, a retry retrieves the same checkout session.
        $response = Http::withBasicAuth($key, '')
            ->acceptJson()->asJson()->timeout(20)
            ->withHeaders(['Idempotency-Key' => $payment->payment_reference])
            ->post(rtrim(config('paymongo.base_url'), '/') . '/v2/checkout_sessions', ['data' => ['attributes' => [
                'line_items' => [[
                    'amount' => 10000,
                    'currency' => 'PHP',
                    'name' => 'KJM Coffee Shop Membership',
                    'quantity' => 1,
                ]],
                'payment_method_types' => ['qrph'],
                'reference_number' => $payment->payment_reference,
                'send_email_receipt' => false,
                'success_url' => $base . '/pay/membership/return',
                'cancel_url' => $base . '/pay/membership/cancel',
                'metadata' => ['membership_payment_id' => (string) $payment->id],
            ]]]);
        if (!$response->successful()) {
            $payment->update(['status' => 'failed']);
            $detail = $response->json('errors.0.detail') ?? 'Payment checkout could not be created.';
            throw new RuntimeException('PayMongo: ' . $detail);
        }
        $payment->update([
            'provider_session_id' => $response->json('data.id'),
            'checkout_url' => $response->json('data.attributes.checkout_url'),
        ]);
        return $this->checkoutPayload($membership->fresh(), $payment->fresh());
    }
    public function verifyPayment(User $user): array
    {
        $membership = Membership::where('user_id', $user->id)->with('payments')->firstOrFail();
        $payment = $membership->payments()->latest('id')->first();
        if ($payment && $payment->status === 'pending' && $payment->provider === 'paymongo' && $payment->provider_session_id) {
            $key = config('paymongo.secret');
            if ($key) {
                $response = Http::withBasicAuth($key, '')->acceptJson()->timeout(20)
                    ->get(rtrim(config('paymongo.base_url'), '/') . '/v1/checkout_sessions/' . $payment->provider_session_id);
                if ($response->successful()) {
                    foreach ((array) $response->json('data.attributes.payments', []) as $providerPayment) {
                        if (data_get($providerPayment, 'attributes.status') === 'paid') {
                            $this->markPaid($payment, data_get($providerPayment, 'id'), (int) data_get($providerPayment, 'attributes.amount'));
                            break;
                        }
                    }
                }
            }
        }
        return $this->customerSummary($user);
    }

    public function markPaidByReference(string $reference, ?string $providerPaymentId, ?int $amountCentavos): bool
    {
        $payment = MembershipPayment::where('payment_reference', $reference)->first();
        if (!$payment) return false;
        $this->markPaid($payment, $providerPaymentId, $amountCentavos);
        return true;
    }

    public function markPaid(MembershipPayment $payment, ?string $providerPaymentId, ?int $amountCentavos): void
    {
        $userId = null;
        $activated = false;
        DB::transaction(function () use ($payment, $providerPaymentId, $amountCentavos, &$userId, &$activated) {
            $p = MembershipPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $m = Membership::whereKey($p->membership_id)->lockForUpdate()->firstOrFail();
            if ($p->status === 'paid') return;
            if ($amountCentavos !== null && $amountCentavos !== (int) round((float) $p->amount * 100)) {
                throw new RuntimeException('Membership payment amount did not match the required ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±100.');
            }
            $p->update(['status' => 'paid', 'provider_payment_id' => $providerPaymentId, 'paid_at' => now()]);
            $m->update(['status' => 'active', 'activated_at' => $m->activated_at ?? now()]);
            $userId = $m->user_id;
            $activated = true;
        });
        if ($activated && $userId) {
            Notifier::send($userId, 'Membership activated', 'Your ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â±100 KJM Coffee Shop membership is active. You can now earn and redeem points.', 'membership', ['member_id' => $payment->membership?->member_id]);
        }
    }

    public function awardForOrder(Order $order): int
    {
        if (!in_array($order->status, ['delivered', 'completed'], true) || $order->payment_status !== 'paid' || !$order->user_id) return 0;
        $qty = (int) $order->items()->sum('quantity');
        if ($qty < 1) return 0;
        $earned = false;
        DB::transaction(function () use ($order, $qty, &$earned) {
            $membership = Membership::where('user_id', $order->user_id)->lockForUpdate()->first();
            if (!$membership || !$membership->isActive()) return;
            if (LoyaltyTransaction::where('order_id', $order->id)->where('type', 'earned')->exists()) return;
            $membership->increment('points_balance', $qty);
            LoyaltyTransaction::create([
                'membership_id' => $membership->id,
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'type' => 'earned',
                'points' => $qty,
                'description' => "Earned from order {$order->order_number}",
            ]);
            $earned = true;
        });
        if ($earned) Notifier::send($order->user_id, 'Loyalty points earned', "You earned {$qty} point(s) from order {$order->order_number}.", 'points', ['order_id' => $order->id, 'points' => $qty]);
        return $earned ? $qty : 0;
    }

    public function customerSummary(User $user): array
    {
        $membership = Membership::where('user_id', $user->id)->with(['payments' => fn ($q) => $q->latest('id')])->first();
        $transactions = $membership ? $membership->transactions()->with('order:id,order_number')->latest('id')->limit(100)->get()->map(fn ($tx) => [
            'id' => $tx->id, 'type' => $tx->type, 'points' => (int) $tx->points,
            'description' => $tx->description, 'order_number' => $tx->order?->order_number,
            'created_at' => $tx->created_at,
        ])->values() : collect();
        $monthly = $membership ? $membership->transactions()->where('type', 'earned')->where('created_at', '>=', now()->startOfMonth())->sum('points') : 0;
        return [
            'membership' => $membership ? [
                'id' => $membership->id, 'member_id' => $membership->member_id,
                'status' => $membership->status, 'active' => $membership->isActive(),
                'points_balance' => (int) $membership->points_balance,
                'activated_at' => $membership->activated_at, 'expires_at' => $membership->expires_at,
                'discount' => $membership->isActive() ? 'No member discount configured' : null,
                'latest_payment' => $membership->payments->first(),
            ] : null,
            'monthly_points' => (int) $monthly,
            'transactions' => $transactions,
        ];
    }

    public function redeem(User $user, Reward $reward, ?string $idempotencyKey): RewardRedemption
    {
        return DB::transaction(function () use ($user, $reward, $idempotencyKey) {
            $membership = Membership::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$membership || !$membership->isActive()) throw new RuntimeException('An active membership is required to redeem rewards.');
            if ($idempotencyKey) {
                $existing = RewardRedemption::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ((int) $existing->reward_id !== (int) $reward->id) throw new RuntimeException('This redemption request key was already used.');
                    return $existing;
                }
            }
            $lockedReward = Reward::whereKey($reward->id)->lockForUpdate()->firstOrFail();
            if (!$lockedReward->isAvailable()) throw new RuntimeException('This reward is currently unavailable.');
            if ($membership->points_balance < $lockedReward->points_cost) throw new RuntimeException('You do not have enough points for this reward.');
            $redemption = RewardRedemption::create([
                'user_id' => $user->id, 'membership_id' => $membership->id, 'reward_id' => $lockedReward->id,
                'reward_name' => $lockedReward->name, 'points_spent' => $lockedReward->points_cost,
                'reward_description' => $lockedReward->description,
                'reward_type' => $lockedReward->reward_type, 'product_id' => $lockedReward->product_id,
                'discount_amount' => $lockedReward->discount_amount,
                'code' => 'KJMR-' . Str::upper(Str::random(16)), 'idempotency_key' => $idempotencyKey,
                'status' => 'pending', 'expires_at' => $lockedReward->expires_at,
            ]);
            $membership->decrement('points_balance', $lockedReward->points_cost);
            LoyaltyTransaction::create([
                'membership_id' => $membership->id, 'user_id' => $user->id, 'redemption_id' => $redemption->id,
                'type' => 'redeemed', 'points' => -$lockedReward->points_cost,
                'description' => "Redeemed {$lockedReward->name} ({$redemption->code})",
            ]);
            if ($lockedReward->stock !== null) $lockedReward->decrement('stock');
            return $redemption->load('reward');
        });
    }

    private function checkoutPayload(Membership $membership, MembershipPayment $payment): array
    {
        return ['membership' => $membership, 'payment' => $payment, 'checkout_url' => $payment->checkout_url];
    }
}