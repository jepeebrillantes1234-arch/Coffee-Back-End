<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use Responds;

    public function index(Request $request)
    {
        return $this->ok(Address::where('user_id', $request->user()->id)->orderByDesc('is_default')->latest('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label'        => 'nullable|string|max:50',
            'full_address' => 'required|string|max:255',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'is_default'   => 'nullable|boolean',
            'notes'        => 'nullable|string|max:255',
        ]);
        $uid = $request->user()->id;
        $isDefault = $request->boolean('is_default');
        if ($isDefault) Address::where('user_id', $uid)->update(['is_default' => false]);

        $a = Address::create($data + ['user_id' => $uid, 'label' => $data['label'] ?? 'Home', 'is_default' => $isDefault]);
        return $this->ok($a, 'Na-save ang address', 201);
    }

    public function destroy(Request $request, int $id)
    {
        Address::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return $this->ok(null, 'Nabura');
    }
}
