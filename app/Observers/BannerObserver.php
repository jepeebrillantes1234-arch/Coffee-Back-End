<?php
namespace App\Observers;
use App\Models\Banner;
class BannerObserver { use BroadcastsModelChanges; public function created(Banner $m):void{ $this->announce($m,'banner',null); } public function updated(Banner $m):void{ if($m->wasChanged()) $this->announce($m,'banner',null); } public function deleted(Banner $m):void{ $this->announce($m,'banner',null); } }
