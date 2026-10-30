<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryChatMessage extends Model
{
    protected $guarded = [];

    public function conversation() { return $this->belongsTo(DeliveryChatConversation::class, 'conversation_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
}
