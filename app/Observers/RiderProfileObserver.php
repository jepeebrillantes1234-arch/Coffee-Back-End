<?php
namespace App\Observers;
use App\Models\RiderProfile;
class RiderProfileObserver { use BroadcastsModelChanges; public function created(RiderProfile $m):void{ $this->announce($m,'rider',(int)$m->user_id ?: null); } public function updated(RiderProfile $m):void{ if($m->wasChanged()) $this->announce($m,'rider',(int)$m->user_id ?: null); } public function deleted(RiderProfile $m):void{ $this->announce($m,'rider',(int)$m->user_id ?: null); } }
