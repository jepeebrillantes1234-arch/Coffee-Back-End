<?php
namespace App\Services;

use App\Models\Membership;
use App\Models\Product;
use App\Models\PromoCode;
use App\Support\Settings;

class MembershipPricing
{
    public function configuration(): array
    {
        return [
            'card_price' => round((float) Settings::get('membership_card_price', 100), 2),
            'discount_percent' => round((float) Settings::get('membership_discount_percent', 10), 2),
            'discount_enabled' => (bool) ((int) Settings::get('membership_discount_enabled', 1)),
        ];
    }

    public function eligible(Membership $membership): bool
    {
        return $membership->isActive() && $membership->payments()->where('status', 'paid')->exists();
    }

    public function eligibleProduct(Product $product): bool
    {
        return !$product->discount_active && (float) $product->sale_price >= (float) $product->base_price;
    }

    public function discountForLines(array $lines, ?Membership $membership, ?PromoCode $promo = null, float $firstOrderDiscount = 0): array
    {
        $config = $this->configuration();
        $eligibleSubtotal = 0.0;
        if ($config['discount_enabled'] && $membership && $this->eligible($membership) && $config['discount_percent'] > 0 && $firstOrderDiscount <= 0) {
            // Percent/fixed codes affect the full subtotal; never stack those on the member benefit.
            $globalPromo = $promo && in_array($promo->type, ['percent', 'fixed'], true);
            if (!$globalPromo) {
                foreach ($lines as $line) {
                    $product = $line['product'] ?? null;
                    if (!$product instanceof Product || !empty($line['home_promo_id']) || !$this->eligibleProduct($product)) continue;
                    $lineTotal = (float) ($line['total'] ?? 0);
                    if ($promo?->type === 'bogo' && (int) $promo->product_id === (int) $product->id) {
                        $sets = intdiv((int) ($line['quantity'] ?? 0), max(1, (int) $promo->buy_qty + (int) $promo->free_qty));
                        $freeUnits = $sets * (int) $promo->free_qty;
                        $lineTotal = max(0, $lineTotal - $freeUnits * (float) ($line['unit_price'] ?? $product->sale_price));
                    }
                    $eligibleSubtotal += $lineTotal;
                }
            }
        }
        $eligibleSubtotal = round($eligibleSubtotal, 2);
        $discount = round(min($eligibleSubtotal, $eligibleSubtotal * $config['discount_percent'] / 100), 2);
        return ['eligible_subtotal' => $eligibleSubtotal, 'discount' => $discount, 'percent' => $discount > 0 ? $config['discount_percent'] : 0.0];
    }
}
