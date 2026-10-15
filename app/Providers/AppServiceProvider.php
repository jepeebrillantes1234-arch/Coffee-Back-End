<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\{Order,Delivery,Payment,Product,OrderItem,Category,PromoCode,Review,Banner,User,RiderProfile};
use App\Observers\{OrderObserver,DeliveryObserver,PaymentObserver,ProductObserver,OrderItemObserver,CategoryObserver,PromoCodeObserver,ReviewObserver,BannerObserver,UserObserver,RiderProfileObserver};

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class); Delivery::observe(DeliveryObserver::class); Payment::observe(PaymentObserver::class); Product::observe(ProductObserver::class); OrderItem::observe(OrderItemObserver::class); Category::observe(CategoryObserver::class); PromoCode::observe(PromoCodeObserver::class); Review::observe(ReviewObserver::class); Banner::observe(BannerObserver::class); User::observe(UserObserver::class); RiderProfile::observe(RiderProfileObserver::class);
    }
}
