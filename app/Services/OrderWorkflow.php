<?php
namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Geo;
use App\Support\Notifier;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

class OrderWorkflow
{
    public function confirm(Order $o) { return $this->change($o,'pending','confirmed'); }
    public function preparing(Order $o) { return $this->change($o,'confirmed','preparing'); }
    public function ready(Order $o)
    {
        $this->change($o, 'preparing', 'ready');
        return $this->assignAvailableRider($o);
    }

    public function assignAvailableRider(Order $o)
    {
        if ($o->order_type !== 'delivery' || $o->status !== 'ready') {
            throw new \DomainException('Only READY delivery orders can be assigned.');
        }

        $rider = User::query()
            ->where('role', 'rider')
            ->where('status', 'active')
            ->whereHas('riderProfile', fn ($query) => $query
                ->where('is_approved', true)
                ->where('is_available', true))
            ->withCount(['deliveries as active_deliveries_count' => fn ($query) => $query
                ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'on_the_way'])])
            ->orderBy('active_deliveries_count')
            ->orderBy('id')
            ->first();

        if (! $rider) {
            return 'This order is ready, but no eligible online rider is available. Bring an approved rider online and try again.';
        }

        $this->assignRider($o, (int) $rider->id);
        return null;
    }
    public function complete(Order $o)
    {
        if (!in_array($o->status,['delivered','ready'],true) || ($o->order_type==='delivery' && $o->status!=='delivered')) throw new \DomainException('This order cannot be completed yet.');
        if ($o->order_type==='delivery' && $o->payment_method==='cod') {
            $o->loadMissing('delivery');
            if (!$o->delivery?->cash_remitted_at) throw new \DomainException('Staff must confirm the rider cash remittance before completing this order.');
        }
        if ($o->order_type==='pickup' && $o->payment_method==='cash' && $o->payment_status!=='paid') {
            $o->update(['payment_status'=>'paid']);
            Payment::updateOrCreate(['order_id'=>$o->id],['method'=>'cash','amount'=>$o->total,'status'=>'paid','paid_at'=>now()]);
        }
        return $this->set($o,'completed');
    }
    public function reject(Order $o,string $reason)
    {
        if (!in_array($o->status,['pending','confirmed'],true)) throw new \DomainException('This order can no longer be rejected.');
        $o->reject_reason=$reason; return $this->set($o,'rejected');
    }
    public function cancel(Order $o,string $reason)
    {
        if (!$o->canCancel()) throw new \DomainException('This order can no longer be cancelled.');
        $o->reject_reason=$reason;
        $this->set($o,'cancelled');
        $delivery = Delivery::with(['rider.riderProfile', 'order'])
            ->where('order_id', $o->id)
            ->whereNotNull('rider_id')
            ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'on_the_way'])
            ->first();
        if ($delivery) Notifier::delivery($delivery, 'cancelled');
        return null;
    }
    public function assignRider(Order $o,int $riderId)
    {
        if ($o->order_type!=='delivery' || $o->status!=='ready') throw new \DomainException('This order is no longer available for assignment.');
        $rider=\App\Models\User::where('role','rider')->where('status','active')->whereHas('riderProfile',fn($q)=>$q->where('is_approved',true))->findOrFail($riderId);
        if (!$rider->riderProfile?->is_available) {
            throw new \DomainException('The selected rider must be ONLINE before a delivery can be assigned.');
        }
        $previousRiderId = null;
        $orderId = (int) $o->id;
        $riderId = (int) $rider->id;
        $delivery = DB::transaction(function () use ($orderId, $riderId, &$previousRiderId) {
            // Serialize assignment attempts for the same order before checking
            // its current state. The unique deliveries.order_id index remains
            // the final database guard against duplicate delivery records.
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($orderId);
            if ($lockedOrder->order_type !== 'delivery' || $lockedOrder->status !== 'ready') {
                throw new \DomainException('This order is no longer available for assignment.');
            }

            $lockedRider = User::query()->lockForUpdate()->findOrFail($riderId);
            $profile = $lockedRider->riderProfile()->lockForUpdate()->first();
            if (!$profile || !$profile->is_approved || !$profile->is_available || $lockedRider->status !== 'active') {
                throw new \DomainException('The selected rider must be active, approved, and ONLINE before a delivery can be assigned.');
            }

            $existingDelivery = Delivery::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();
            if ($existingDelivery && !in_array($existingDelivery->status, ['assigned', 'declined'], true)) {
                throw new \DomainException('This delivery is already in progress or completed and cannot be reassigned.');
            }

            $delivery = $existingDelivery ?? new Delivery(['order_id' => $lockedOrder->id]);
            $previousRiderId = $delivery->rider_id ? (int) $delivery->rider_id : null;
            $delivery->rider_id = $lockedRider->id;
            $delivery->status = 'assigned';
            $delivery->assigned_at = now();
            if ($delivery->distance_km === null) {
                $delivery->distance_km = $this->estimatedDistance($lockedOrder);
            }
            $delivery->save();
            $this->set($lockedOrder, 'rider_assigned');
            return $delivery->fresh();
        });
        $delivery->loadMissing(['rider.riderProfile', 'order']);
        Notifier::delivery($delivery, $previousRiderId && $previousRiderId !== (int) $rider->id ? 'reassigned' : 'assigned', true);
        return null;
    }

    private function estimatedDistance(Order $order): ?float
    {
        $shopLat = Settings::get('shop_lat');
        $shopLng = Settings::get('shop_lng');
        if (!is_numeric($shopLat) || !is_numeric($shopLng)
            || !is_numeric($order->delivery_lat) || !is_numeric($order->delivery_lng)) {
            return null;
        }

        return Geo::km((float) $shopLat, (float) $shopLng, (float) $order->delivery_lat, (float) $order->delivery_lng);
    }
    private function change(Order $o,string $from,string $to)
    {
        if($o->status!==$from) throw new \DomainException('The order status has changed. Refresh the page and try again.');
        return $this->set($o,$to);
    }
    private function set(Order $o,string $status)
    {
        $o->status=$status; $o->save();
        DB::table('order_status_logs')->insert(['order_id'=>$o->id,'status'=>$status,'changed_by'=>auth()->id(),'created_at'=>now()]);
        if (in_array($status, ['cancelled', 'rejected'], true)) {
            app(LoyaltyService::class)->restoreForOrder($o);
        }
        Notifier::order($o,$status); return null;
    }
}


