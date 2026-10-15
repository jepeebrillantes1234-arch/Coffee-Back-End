<?php
namespace App\Observers;
use App\Models\Category;
class CategoryObserver { use BroadcastsModelChanges; public function created(Category $m):void{ $this->announce($m,'category',null); } public function updated(Category $m):void{ if($m->wasChanged()) $this->announce($m,'category',null); } public function deleted(Category $m):void{ $this->announce($m,'category',null); } }
