<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $guarded = [];

    public function conversation() { return $this->belongsTo(SupportConversation::class, 'conversation_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
}