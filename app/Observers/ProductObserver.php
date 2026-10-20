<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\FcmPushService;

class ProductObserver
{
    use BroadcastsModelChanges;

    public function created(Product $product): void
    {
        $this->announce($product, 'product');
        if ($product->is_available) {
            app(FcmPushService::class)->sendToCustomers(
                'New product',
                $product->name.' is now on the menu. Tap to explore the menu.',
                ['type' => 'product', 'product_id' => (string) $product->id]
            );
        }
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged()) {
            $this->announce($product, 'product');
        }
    }

    public function deleted(Product $product): void
    {
        $this->announce($product, 'product');
    }
}