<?php
namespace App\Observers;
use App\Models\Product;
class ProductObserver { use BroadcastsModelChanges; public function created(Product $m):void{$this->announce($m,'product');} public function updated(Product $m):void{if($m->wasChanged())$this->announce($m,'product');} public function deleted(Product $m):void{$this->announce($m,'product');} }
