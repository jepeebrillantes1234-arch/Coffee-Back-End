<?php
namespace App\Observers;
use App\Models\OrderItem;
class OrderItemObserver { use BroadcastsModelChanges; public function created(OrderItem $m):void{$m->loadMissing('order');$this->announce($m,'order',(int)optional($m->order)->user_id?:null);} public function updated(OrderItem $m):void{$m->loadMissing('order');$this->announce($m,'order',(int)optional($m->order)->user_id?:null);} public function deleted(OrderItem $m):void{$m->loadMissing('order');$this->announce($m,'order',(int)optional($m->order)->user_id?:null);} }
