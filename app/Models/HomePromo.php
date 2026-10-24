<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomePromo extends Model
{
    protected $fillable = ['name', 'description', 'price', 'image', 'is_active', 'starts_at', 'ends_at', 'sort_order'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    protected $appends = ['image_url'];

    public function products() { return $this->belongsToMany(Product::class, 'home_promo_items')->withPivot('quantity'); }
    public function scopeLive($query) { return $query->where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now())); }
    public function getImageUrlAttribute(): ?string { return $this->image ? Storage::disk('public')->url($this->image) : null; }
}
