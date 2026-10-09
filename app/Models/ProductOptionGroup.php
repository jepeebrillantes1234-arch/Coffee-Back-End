<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOptionGroup extends Model
{
    protected $fillable = ['product_id','name','is_required','is_multiple','max_select'];
    protected $casts = ['is_required' => 'boolean', 'is_multiple' => 'boolean'];

    public function product() { return $this->belongsTo(Product::class); }
    public function options()  { return $this->hasMany(ProductOption::class, 'group_id'); }
}
