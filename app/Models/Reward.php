<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Reward extends Model
{
    protected $guarded = [];
    protected $casts = ['points_cost' => 'integer', 'stock' => 'integer', 'discount_amount' => 'decimal:2', 'is_active' => 'boolean', 'expires_at' => 'datetime'];
    protected $appends = ['image_url'];
    public function getImageUrlAttribute(): ?string { return $this->image ? Storage::disk('public')->url($this->image) : null; }
    public function product() { return $this->belongsTo(Product::class); }
    public function redemptions() { return $this->hasMany(RewardRedemption::class); }
    public function isAvailable(): bool { return $this->is_active && (!$this->expires_at || $this->expires_at->isFuture()) && ($this->stock === null || $this->stock > 0); }
}