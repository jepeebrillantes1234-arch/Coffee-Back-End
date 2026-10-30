<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $guarded = [];
    protected $casts = ['is_default' => 'boolean'];

    public function getFormattedAddressAttribute($value): ?string
    {
        return $value ?: ($this->attributes['full_address'] ?? null);
    }
}
