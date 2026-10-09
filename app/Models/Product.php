<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id','name','description','base_price','image','is_available','avg_rating'];
    protected $casts = ['is_available' => 'boolean', 'base_price' => 'decimal:2'];
    protected $appends = ['image_url'];

    public function category()     { return $this->belongsTo(Category::class); }
    public function optionGroups() { return $this->hasMany(ProductOptionGroup::class); }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.ltrim($this->image, '/')) : null;
    }
}
