<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Reward extends Model
{
    protected $guarded = [];
    protected $casts = ['points_cost' => 'integer', 'stock' => 'integer', 'discount_amount' => 'decimal:2', 'is_active' => 'boolean', 'expires_at' => 'datetime'];
    protected $appends = ['image_url'];
    public function getImageUrlAttribute(): ?string
    {
        $image = trim((string) $this->image);
        if ($image === '') {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        $image = ltrim(str_replace('\\', '/', $image), '/');
        $image = preg_replace('#^(?:public/|storage/)#', '', $image);

        if ($image === '' || ! Storage::disk('public')->exists($image)) {
            return null;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $image)));

        return url('/storage/' . $encodedPath);
    }
    public function product() { return $this->belongsTo(Product::class); }
    public function redemptions() { return $this->hasMany(RewardRedemption::class); }
    public function isAvailable(): bool { return $this->is_active && (!$this->expires_at || $this->expires_at->isFuture()) && ($this->stock === null || $this->stock > 0); }
}