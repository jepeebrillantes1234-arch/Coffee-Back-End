<?php
namespace App\Observers;
use App\Models\Delivery;
class DeliveryObserver { use BroadcastsModelChanges; public function created(Delivery $m):void{$m->loadMissing('order');$this->announce($m,'delivery',(int)optional($m->order)->user_id?:null);} public function updated(Delivery $m):void{if($m->wasChanged()){$m->loadMissing('order');$this->announce($m,'delivery',(int)optional($m->order)->user_id?:null);}} }
