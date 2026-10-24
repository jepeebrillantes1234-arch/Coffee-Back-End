<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id','name','description','base_price','image','is_available','avg_rating','discount_type','discount_value','discount_starts_at','discount_ends_at','discount_is_active'];
    protected $casts = ['is_available' => 'boolean', 'base_price' => 'decimal:2', 'discount_value' => 'decimal:2', 'discount_is_active' => 'boolean', 'discount_starts_at' => 'datetime', 'discount_ends_at' => 'datetime'];
    protected $appends = ['image_url','sale_price','discount_amount','discount_percent','discount_active'];

    public function category()     { return $this->belongsTo(Category::class); }
    public function optionGroups() { return $this->hasMany(ProductOptionGroup::class); }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.ltrim($this->image, '/')) : null;
    }

    public function getDiscountActiveAttribute(): bool
    {
        return (bool)$this->discount_is_active && $this->discount_type && (float)$this->discount_value > 0
            && (!$this->discount_starts_at || $this->discount_starts_at->lte(now()))
            && (!$this->discount_ends_at || $this->discount_ends_at->gte(now()));
    }
    public function getSalePriceAttribute(): float
    {
        $base=(float)$this->base_price;
        if (!$this->discount_active) return $base;
        $amount=$this->discount_type==='percent' ? $base*(float)$this->discount_value/100 : (float)$this->discount_value;
        return round(max(0,$base-min($base,$amount)),2);
    }
    public function getDiscountAmountAttribute(): float { return round((float)$this->base_price-$this->sale_price,2); }
    public function getDiscountPercentAttribute(): float { return (float)$this->base_price > 0 ? round($this->discount_amount/(float)$this->base_price*100,1) : 0; }
}
