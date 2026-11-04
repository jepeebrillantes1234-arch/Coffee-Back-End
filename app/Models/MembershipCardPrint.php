<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipCardPrint extends Model
{
    protected $guarded = [];

    protected $casts = ['printed_at' => 'datetime'];

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}