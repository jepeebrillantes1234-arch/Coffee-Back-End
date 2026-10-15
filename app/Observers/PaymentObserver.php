<?php
namespace App\Observers;
use App\Models\Payment;
class PaymentObserver { use BroadcastsModelChanges; public function created(Payment $m):void{$m->loadMissing('order');$this->announce($m,'payment',(int)optional($m->order)->user_id?:null);} public function updated(Payment $m):void{if($m->wasChanged()){$m->loadMissing('order');$this->announce($m,'payment',(int)optional($m->order)->user_id?:null);}} }
