<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportConversation extends Model
{
    protected $guarded = [];

    protected $casts = ['last_message_at' => 'datetime', 'human_replied_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function refundOrder() { return $this->belongsTo(Order::class, 'refund_order_id'); }
    public function messages() { return $this->hasMany(SupportMessage::class, 'conversation_id'); }
}