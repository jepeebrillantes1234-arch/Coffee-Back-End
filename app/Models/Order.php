<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['is_first_order' => 'boolean'];

    public const CANCELLABLE = ['awaiting_payment','pending','confirmed','preparing','ready'];

    public function user()     { return $this->belongsTo(User::class); }
    public function staff()    { return $this->belongsTo(User::class, 'staff_id'); }
    public function items()    { return $this->hasMany(OrderItem::class); }
    public function delivery() { return $this->hasOne(Delivery::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function review()   { return $this->hasOne(Review::class); }
    public function rewardRedemption() { return $this->hasOne(RewardRedemption::class); }

    public function canCancel(): bool { return in_array($this->status, self::CANCELLABLE, true); }
}

