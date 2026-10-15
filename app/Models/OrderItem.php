<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class OrderItem extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function order() { return $this->belongsTo(Order::class); }
    public function options() { return $this->hasMany(OrderItemOption::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
