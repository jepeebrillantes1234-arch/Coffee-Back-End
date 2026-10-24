<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\HomePromo;
use App\Models\Product;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class HomePromoController extends Controller {
 public function index(){ $promos=HomePromo::with('products')->orderBy('sort_order')->latest()->paginate(20); $products=Product::where('is_available',true)->orderBy('name')->get(); return view('admin.home-promos.index',compact('promos','products')); }
 public function edit(HomePromo $homePromo){$homePromo->load('products');$products=Product::where('is_available',true)->orderBy('name')->get();return view('admin.home-promos.edit',compact('homePromo','products'));}
 public function store(Request $r){ $d=$this->validated($r); DB::transaction(function()use($r,$d){$p=HomePromo::create(collect($d)->except('items')->all());$p->products()->sync($d['items']);if($r->hasFile('image'))$p->update(['image'=>$r->file('image')->store('home-promos','public')]);});return back()->with('success','Home bundle saved.'); }
 public function update(Request $r,HomePromo $homePromo){$d=$this->validated($r);DB::transaction(function()use($r,$d,$homePromo){$homePromo->update(collect($d)->except('items')->all());$homePromo->products()->sync($d['items']);if($r->hasFile('image')){if($homePromo->image)Storage::disk('public')->delete($homePromo->image);$homePromo->update(['image'=>$r->file('image')->store('home-promos','public')]);}});return back()->with('success','Home bundle updated.');}
 public function destroy(HomePromo $homePromo){if($homePromo->image)Storage::disk('public')->delete($homePromo->image);$homePromo->delete();return back()->with('success','Home bundle deleted.');}
 public function bestSellers(Request $r){Settings::set('home_best_sellers_enabled',$r->boolean('enabled')?'1':'0');return back()->with('success','Best sellers visibility updated.');}
 private function validated(Request $r):array{$d=$r->validate(['name'=>'required|string|max:150','description'=>'nullable|string|max:1000','price'=>'required|numeric|min:0.01|max:999999','image'=>'nullable|image|max:4096','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','sort_order'=>'nullable|integer|min:0','items'=>'required|array|min:1','items.*.product_id'=>'required|integer|distinct|exists:products,id','items.*.quantity'=>'required|integer|min:1|max:50']);$items=[];$original=0;foreach($d['items'] as $item){$p=Product::where('is_available',true)->find($item['product_id']);if(!$p)throw ValidationException::withMessages(['items'=>'Bundles can only contain available products.']);$items[$p->id]=['quantity'=>(int)$item['quantity']];$original+=(float)$p->base_price*(int)$item['quantity'];}if((float)$d['price'] >= $original)throw ValidationException::withMessages(['price'=>'Bundle price must be lower than the combined product price.']);$d['items']=$items;$d['is_active']=$r->boolean('is_active');$d['sort_order']=(int)($d['sort_order']??0);unset($d['image']);return $d;}
}
