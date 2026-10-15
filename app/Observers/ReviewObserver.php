<?php
namespace App\Observers;
use App\Models\Review;
class ReviewObserver { use BroadcastsModelChanges; public function created(Review $m):void{ $this->announce($m,'review',null); } public function updated(Review $m):void{ if($m->wasChanged()) $this->announce($m,'review',null); } public function deleted(Review $m):void{ $this->announce($m,'review',null); } }
