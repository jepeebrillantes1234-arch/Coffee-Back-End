<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\RewardRedemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipController extends Controller
{
    public function index(Request $request)
    {
        $members = Membership::with(['user:id,name,email,phone', 'payments' => fn ($q) => $q->latest('id')])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $q->where(function ($matches) use ($term) {
                    $matches->where('member_id', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('payment_status'), fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', $request->payment_status)))
            ->latest('id')->paginate(20)->withQueryString();
        return view('admin.memberships.index', compact('members'));
    }

    public function show(Membership $membership)
    {
        $membership->load(['user', 'payments' => fn ($q) => $q->latest('id'), 'transactions.order:id,order_number', 'redemptions.reward']);
        return view('admin.memberships.show', compact('membership'));
    }

    public function toggle(Membership $membership)
    {
        if ($membership->status === 'active') {
            $membership->update(['status' => 'inactive']);
            return back()->with('success', 'Membership deactivated.');
        }
        $paid = $membership->payments()->where('status', 'paid')->exists();
        if (!$paid) return back()->with('error', 'A pending or failed payment cannot activate a membership.');
        $membership->update(['status' => 'active', 'activated_at' => $membership->activated_at ?? now()]);
        return back()->with('success', 'Membership activated.');
    }

    public function claim(Request $request, RewardRedemption $redemption)
    {
        $claimed = DB::transaction(function () use ($redemption, $request) {
            $locked = RewardRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending' || ($locked->expires_at && $locked->expires_at->isPast())) return false;
            $locked->update(['status' => 'claimed', 'claimed_by' => $request->user()->id, 'claimed_at' => now()]);
            return true;
        });
        return $claimed ? back()->with('success', 'Reward marked as claimed.') : back()->with('error', 'This reward is already claimed or no longer valid.');
    }
}