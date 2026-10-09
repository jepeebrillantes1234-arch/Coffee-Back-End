<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name','image','is_active','sort_order'];
    protected $casts = ['is_active' => 'boolean'];
    protected $appends = ['image_url'];

    public function products() { return $this->hasMany(Product::class); }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.ltrim($this->image, '/')) : null;
    }
}
