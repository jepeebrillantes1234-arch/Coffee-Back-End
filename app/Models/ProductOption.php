<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $fillable = ['group_id','name','extra_price','is_available'];
    protected $casts = ['is_available' => 'boolean'];

    public function group() { return $this->belongsTo(ProductOptionGroup::class, 'group_id'); }
}
