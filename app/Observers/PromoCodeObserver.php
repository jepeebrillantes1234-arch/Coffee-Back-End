<?php
namespace App\Observers;
use App\Models\PromoCode;
class PromoCodeObserver { use BroadcastsModelChanges; public function created(PromoCode $m):void{ $this->announce($m,'promo',null); } public function updated(PromoCode $m):void{ if($m->wasChanged()) $this->announce($m,'promo',null); } public function deleted(PromoCode $m):void{ $this->announce($m,'promo',null); } }
