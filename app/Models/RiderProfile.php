<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderProfile extends Model
{
    protected $fillable = ['user_id','vehicle_type','plate_number','license_no',
        'is_approved','is_available','current_lat','current_lng','last_location_at'];
    protected $casts = ['is_approved' => 'boolean', 'is_available' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
