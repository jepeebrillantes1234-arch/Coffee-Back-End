<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundAttempt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'idempotency_key_created_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'provider_created_at' => 'datetime',
        'provider_updated_at' => 'datetime',
    ];

    public function refundRequest() { return $this->belongsTo(RefundRequest::class); }
}
