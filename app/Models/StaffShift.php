<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffShift extends Model
{
    protected $guarded = [];

    protected $casts = [
        'opening_fund' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'online_payments' => 'decimal:2',
        'cash_refunds' => 'decimal:2',
        'cash_deductions' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function staff() { return $this->belongsTo(User::class, 'user_id'); }
    public function closedBy() { return $this->belongsTo(User::class, 'closed_by'); }
    public function orders() { return $this->hasMany(Order::class); }
    public function isOpen(): bool { return $this->status === 'open'; }
}
