<?php
namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Support\Notifier;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Support\Geo;
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
        if (RewardRedemption::where('order_id', $order->id)->exists()) return 0;
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
        $cardDesignActive = Settings::get('membership_card_design_active', '0') === '1';
        $frontCardPath = Settings::get('membership_card_front_design')
            ?: Settings::get('membership_card_design');
        $backCardPath = Settings::get('membership_card_back_design');
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
            // Preserve the original key for older app versions.
            'card_design_url' => $cardDesignActive && filled($frontCardPath)
                ? Storage::disk('public')->url($frontCardPath) : null,
            'card_design_front_url' => $cardDesignActive && filled($frontCardPath)
                ? Storage::disk('public')->url($frontCardPath) : null,
            'card_design_back_url' => $cardDesignActive && filled($backCardPath)
                ? Storage::disk('public')->url($backCardPath) : null,
        ];
    }

    public function deliveryQuote(User $user, Reward $reward, int $addressId): array
    {
        $membership = Membership::where('user_id', $user->id)->first();
        if (!$membership || !$membership->isActive()) throw new RuntimeException('An active membership is required to redeem points.');
        if (!$reward->isAvailable() || $reward->reward_type !== 'free_product') throw new RuntimeException('This product reward is unavailable.');
        if ($membership->points_balance < $reward->points_cost) throw new RuntimeException('You do not have enough points for this reward.');
        $product = Product::whereKey($reward->product_id)->where('is_available', true)->first();
        if (!$product) throw new RuntimeException('This product is currently unavailable.');
        $address = Address::where('id', $addressId)->where('user_id', $user->id)->firstOrFail();
        return $this->quoteDelivery($address);
    }

    private function quoteDelivery(Address $address): array
    {
        $shopLat = Settings::get('shop_lat'); $shopLng = Settings::get('shop_lng');
        if (!is_numeric($shopLat) || !is_numeric($shopLng) || !is_numeric($address->latitude) || !is_numeric($address->longitude)) {
            throw new RuntimeException('Delivery location is missing valid coordinates.');
        }
        $distance = Geo::km((float) $shopLat, (float) $shopLng, (float) $address->latitude, (float) $address->longitude);
        if ($distance > (float) Settings::get('delivery_radius_km', 8)) throw new RuntimeException('This address is outside the delivery area.');
        return ['delivery_fee' => round((float) Settings::get('delivery_base_fee', 30) + $distance * (float) Settings::get('delivery_fee_per_km', 10), 2), 'distance_km' => round($distance, 2)];
    }

    public function redeem(User $user, Reward $reward, ?string $idempotencyKey, string $fulfillmentType = 'pickup', ?int $addressId = null): RewardRedemption
    {
        return DB::transaction(function () use ($user, $reward, $idempotencyKey, $fulfillmentType, $addressId) {
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
            if ($lockedReward->reward_type === 'free_product') {
                if (!in_array($fulfillmentType, ['delivery', 'pickup'], true)) throw new RuntimeException('Choose delivery or store pickup.');
                $product = Product::whereKey($lockedReward->product_id)->lockForUpdate()->first();
                if (!$product || !$product->is_available) throw new RuntimeException('This product is currently unavailable.');
                $address = null; $deliveryFee = 0; $order = null;
                if ($fulfillmentType === 'delivery') {
                    if (!$addressId) throw new RuntimeException('Choose a saved delivery address.');
                    $address = Address::where('id', $addressId)->where('user_id', $user->id)->firstOrFail();
                    $deliveryFee = $this->quoteDelivery($address)['delivery_fee'];
                }
            } else {
                $fulfillmentType = 'pickup'; $deliveryFee = 0; $product = null; $address = null; $order = null;
            }
            $redemption = RewardRedemption::create([
                'user_id' => $user->id, 'membership_id' => $membership->id, 'reward_id' => $lockedReward->id,
                'reward_name' => $lockedReward->name, 'points_spent' => $lockedReward->points_cost,
                'reward_description' => $lockedReward->description,
                'reward_type' => $lockedReward->reward_type, 'product_id' => $lockedReward->product_id,
                'discount_amount' => $lockedReward->discount_amount,
                'code' => 'KJMR-' . Str::upper(Str::random(16)), 'idempotency_key' => $idempotencyKey,
                'status' => 'pending', 'expires_at' => $lockedReward->expires_at,
                'fulfillment_type' => $fulfillmentType, 'delivery_fee' => $deliveryFee,
            ]);
            if ($product) {
                $order = Order::create([
                    'order_number' => 'KJM-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                    'user_id' => $user->id, 'order_type' => $fulfillmentType,
                    'status' => 'pending', 'subtotal' => 0, 'discount' => 0,
                    'delivery_fee' => $deliveryFee, 'total' => $deliveryFee,
                    'payment_method' => $fulfillmentType === 'delivery' ? 'cod' : 'cash',
                    'payment_status' => 'unpaid',
                    'delivery_address' => $address?->full_address,
                    'delivery_lat' => $address?->latitude, 'delivery_lng' => $address?->longitude,
                    'notes' => 'Points redemption ' . $redemption->code . ($fulfillmentType === 'pickup' ? ' - store pickup' : ''),
                ]);
                $order->items()->create([
                    'product_id' => $product->id, 'product_name' => $product->name,
                    'quantity' => 1, 'unit_price' => 0, 'total_price' => 0,
                    'notes' => 'Redeemed with ' . $lockedReward->points_cost . ' points',
                ]);
                DB::table('order_status_logs')->insert(['order_id' => $order->id, 'status' => 'pending', 'changed_by' => $user->id, 'created_at' => now()]);
                if ($fulfillmentType === 'delivery' && $deliveryFee > 0) {
                    Payment::create(['order_id' => $order->id, 'method' => 'cod', 'amount' => $deliveryFee, 'status' => 'pending']);
                }
                $redemption->update(['order_id' => $order->id]);
            }
            $membership->decrement('points_balance', $lockedReward->points_cost);
            LoyaltyTransaction::create([
                'membership_id' => $membership->id, 'user_id' => $user->id, 'redemption_id' => $redemption->id,
                'type' => 'redeemed', 'points' => -$lockedReward->points_cost,
                'description' => "Redeemed {$lockedReward->name} ({$redemption->code})",
            ]);
            if ($lockedReward->stock !== null) $lockedReward->decrement('stock');
            if ($order) Notifier::order($order, 'pending');
            return $redemption->load(['reward', 'order']);
        });
    }

    public function restoreForOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $redemption = RewardRedemption::where('order_id', $order->id)->lockForUpdate()->first();
            if (!$redemption || $redemption->status !== 'pending') return false;
            $membership = Membership::whereKey($redemption->membership_id)->lockForUpdate()->first();
            if (!$membership) return false;
            $redemption->update(['status' => 'cancelled']);
            $membership->increment('points_balance', $redemption->points_spent);
            if ($redemption->reward_id) {
                $reward = Reward::whereKey($redemption->reward_id)->lockForUpdate()->first();
                if ($reward && $reward->stock !== null) $reward->increment('stock');
            }
            LoyaltyTransaction::create([
                'membership_id' => $membership->id, 'user_id' => $membership->user_id,
                'redemption_id' => $redemption->id, 'type' => 'adjustment',
                'points' => $redemption->points_spent,
                'description' => "Points restored for cancelled redemption {$redemption->code}",
            ]);
            return true;
        });
    }

    public function cancelRedemption(User $user, RewardRedemption $redemption): RewardRedemption
    {
        return DB::transaction(function () use ($user, $redemption) {
            $locked = RewardRedemption::whereKey($redemption->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') throw new RuntimeException('This redemption can no longer be cancelled.');
            if ($locked->order_id) {
                $order = Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
                if (!$order->canCancel()) throw new RuntimeException('This order can no longer be cancelled.');
                $order->update(['status' => 'cancelled', 'reject_reason' => 'Customer cancelled points redemption']);
                DB::table('order_status_logs')->insert(['order_id' => $order->id, 'status' => 'cancelled', 'changed_by' => $user->id, 'created_at' => now()]);
                $this->restoreForOrder($order);
                Notifier::order($order, 'cancelled');
            } else {
                $membership = Membership::whereKey($locked->membership_id)->lockForUpdate()->firstOrFail();
                $locked->update(['status' => 'cancelled']);
                $membership->increment('points_balance', $locked->points_spent);
                LoyaltyTransaction::create(['membership_id' => $membership->id, 'user_id' => $user->id, 'redemption_id' => $locked->id, 'type' => 'adjustment', 'points' => $locked->points_spent, 'description' => "Points restored for cancelled redemption {$locked->code}"]);
                if ($locked->reward_id) {
                    $reward = Reward::whereKey($locked->reward_id)->lockForUpdate()->first();
                    if ($reward && $reward->stock !== null) $reward->increment('stock');
                }
            }
            return $locked->fresh(['order', 'reward']);
        });
    }

    private function checkoutPayload(Membership $membership, MembershipPayment $payment): array
    {
        return ['membership' => $membership, 'payment' => $payment, 'checkout_url' => $payment->checkout_url];
    }
}
