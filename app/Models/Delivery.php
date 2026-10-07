<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $guarded = [];

    protected $casts = [
        'assigned_at' => 'datetime', 'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime', 'delivered_at' => 'datetime',
        'cash_collected_amount' => 'decimal:2', 'cash_collected_at' => 'datetime',
        'cash_remitted_at' => 'datetime',
    ];

    protected $appends = ['proof_photo_url'];

    public function order() { return $this->belongsTo(Order::class); }
    public function rider() { return $this->belongsTo(User::class, 'rider_id'); }
    public function remittedBy() { return $this->belongsTo(User::class, 'cash_remitted_by'); }

    public function getProofPhotoUrlAttribute(): ?string
    {
        return $this->proof_photo ? asset('storage/' . ltrim($this->proof_photo, '/')) : null;
    }
}
