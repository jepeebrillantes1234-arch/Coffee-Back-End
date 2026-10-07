<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RiderProfile;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request, string $role)
    {
        $users = User::where('role', $role)
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
            ->when($role === 'customer', fn ($q) => $q->withCount('orders'))
            ->when($role === 'rider', fn ($q) => $q->with('riderProfile')->withCount('deliveries'))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users', 'role'));
    }

    public function create(string $role)
    {
        return view('admin.users.form', ['user' => new User(['role' => $role, 'status' => 'active']), 'role' => $role]);
    }

    public function store(Request $request, string $role)
    {
        $data = $request->validate($this->rules($role));

        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'password' => $data['password'], 'role' => $role, 'status' => 'active',
        ]);

        if ($role === 'rider') {
            RiderProfile::create([
                'user_id' => $user->id, 'vehicle_type' => $data['vehicle_type'] ?? null,
                'plate_number' => $data['plate_number'] ?? null, 'license_no' => $data['license_no'] ?? null,
                'is_approved' => true,
            ]);
        }

        Activity::log('create', $role, "Nagdagdag ng {$role}: {$user->name}");
        return redirect()->route('admin.users.index', $role)->with('success', 'Naidagdag ang account.');
    }

    public function edit(User $user)
    {
        abort_unless(in_array($user->role, ['staff', 'rider']), 404);
        $user->load('riderProfile');
        return view('admin.users.form', ['user' => $user, 'role' => $user->role]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(in_array($user->role, ['staff', 'rider']), 404);
        $data = $request->validate($this->rules($user->role, $user));

        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        if (!empty($data['password'])) $user->password = $data['password'];
        $user->save();

        if ($user->role === 'rider') {
            RiderProfile::updateOrCreate(['user_id' => $user->id], [
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'plate_number' => $data['plate_number'] ?? null,
                'license_no'   => $data['license_no'] ?? null,
            ]);
        }

        Activity::log('update', $user->role, "Ni-update ang {$user->role}: {$user->name}");
        return redirect()->route('admin.users.index', $user->role)->with('success', 'Na-update ang account.');
    }

    // Block / Unblock / Deactivate
    public function toggle(User $user)
    {
        if ($user->id === auth()->id() || $user->role === 'admin') {
            return back()->with('error', 'Hindi puwedeng baguhin ang account na ito.');
        }

        $user->status = $user->status === 'active' ? 'blocked' : 'active';
        $user->save();

        if ($user->status !== 'active') {
            $user->tokens()->delete(); // i-logout sa lahat ng device
            if ($user->role === 'rider') RiderProfile::where('user_id', $user->id)->update(['is_available' => false]);
        }

        Activity::log($user->status === 'active' ? 'unblock' : 'block', $user->role, "{$user->name} ay naging {$user->status}");
        return back()->with('success', "Ang account ay {$user->status} na.");
    }

    // Rider approval
    public function approve(User $user)
    {
        abort_unless($user->role === 'rider', 404);
        $profile = RiderProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->is_approved = !$profile->is_approved;
        if (!$profile->is_approved) $profile->is_available = false;
        $profile->save();

        Activity::log('approve', 'rider', $user->name . ($profile->is_approved ? ' ay naaprubahan' : ' ay na-unapprove'));
        return back()->with('success', $profile->is_approved ? 'Naaprubahan ang rider.' : 'Na-unapprove ang rider.');
    }

    private function rules(string $role, ?User $user = null): array
    {
        $rules = [
            'name'     => 'required|string|max:100',
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone'    => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ];
        if ($role === 'rider') {
            $rules += ['vehicle_type' => 'nullable|string|max:50', 'plate_number' => 'nullable|string|max:20', 'license_no' => 'nullable|string|max:50'];
        }
        return $rules;
    }
}
