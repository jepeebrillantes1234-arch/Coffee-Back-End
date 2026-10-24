<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\RewardRedemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardRedemptionController extends Controller
{
    public function index() { return view('staff.rewards.verify', ['redemption' => null]); }

    public function claim(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:48']);
        $redemption = RewardRedemption::with(['user:id,name,email', 'reward:id,name'])
            ->where('code', strtoupper(trim($data['code'])))->first();
        if (!$redemption) return back()->withInput()->with('error', 'No reward redemption matches that code.');
        $claimed = DB::transaction(function () use ($redemption, $request) {
            $locked = RewardRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending' || ($locked->expires_at && $locked->expires_at->isPast())) return false;
            $locked->update(['status' => 'claimed', 'claimed_by' => $request->user()->id, 'claimed_at' => now()]);
            return true;
        });
        $redemption->refresh();
        $redemption->load(['user:id,name,email', 'reward:id,name']);
        return view('staff.rewards.verify', compact('redemption'))->with(
            $claimed ? 'success' : 'error',
            $claimed ? 'Reward successfully verified and marked as claimed.' : 'This redemption was already claimed or is no longer valid.'
        );
    }
}