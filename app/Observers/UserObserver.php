<?php
namespace App\Observers;
use App\Models\User;
class UserObserver { use BroadcastsModelChanges; public function created(User $m):void{ $this->announce($m,'user',null); } public function updated(User $m):void{ if($m->wasChanged()) $this->announce($m,'user',null); } public function deleted(User $m):void{ $this->announce($m,'user',null); } }
