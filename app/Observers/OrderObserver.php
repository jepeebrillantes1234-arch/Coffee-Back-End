<?php
namespace App\Observers;
use App\Models\Order;
use App\Services\LoyaltyService;
class OrderObserver
{
    use BroadcastsModelChanges;
    public function created(Order $m): void { $this->announce($m, 'order', (int) $m->user_id ?: null); }
    public function updated(Order $m): void
    {
        if ($m->wasChanged(['status', 'payment_status', 'total', 'order_type'])) $this->announce($m, 'order', (int) $m->user_id ?: null);
        if ($m->wasChanged(['status', 'payment_status'])) app(LoyaltyService::class)->awardForOrder($m);
    }
    public function deleted(Order $m): void { $this->announce($m, 'order', (int) $m->user_id ?: null); }
}