<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryChatConversation extends Model
{
    protected $guarded = [];

    public function delivery() { return $this->belongsTo(Delivery::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function rider() { return $this->belongsTo(User::class, 'rider_id'); }
    public function messages() { return $this->hasMany(DeliveryChatMessage::class, 'conversation_id')->orderBy('id'); }
}
