<?php

namespace App\Observers;

use App\Models\PromoCode;
use App\Services\FcmPushService;

class PromoCodeObserver
{
    use BroadcastsModelChanges;

    public function created(PromoCode $promo): void
    {
        $this->announce($promo, 'promo', null);
        if ($promo->is_active && $promo->show_in_app) {
            app(FcmPushService::class)->sendToCustomers(
                'New promotion',
                'A new offer is available. Tap to view the latest promotions.',
                ['type' => 'promo', 'promo_id' => (string) $promo->id]
            );
        }
    }

    public function updated(PromoCode $promo): void
    {
        if ($promo->wasChanged()) {
            $this->announce($promo, 'promo', null);
        }
    }

    public function deleted(PromoCode $promo): void
    {
        $this->announce($promo, 'promo', null);
    }
}