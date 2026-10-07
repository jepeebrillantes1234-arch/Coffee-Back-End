<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PromoCode extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean', 'show_in_app' => 'boolean',
        'starts_at' => 'datetime', 'expires_at' => 'datetime',
    ];
    protected $appends = ['image_url'];

    public function usages()  { return $this->hasMany(PromoUsage::class, 'promo_id'); }
    public function product() { return $this->belongsTo(Product::class); }

    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /** Aktibo at nasa loob ng iskedyul */
    public function scopeLive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }
}
