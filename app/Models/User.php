<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name','username','email','phone','password','role','avatar','status','fcm_token','google_id','firebase_uid'];
    protected $hidden   = ['password','remember_token'];
    protected $casts    = ['password' => 'hashed'];

    public function riderProfile() { return $this->hasOne(RiderProfile::class); }
    public function orders()       { return $this->hasMany(Order::class); }
    public function deliveries()   { return $this->hasMany(Delivery::class, 'rider_id'); }

    public function isRole(string $r): bool { return $this->role === $r; }
}
