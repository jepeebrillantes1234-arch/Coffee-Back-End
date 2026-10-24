<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class LoyaltyController extends Controller
{
    public function summary(Request $request, LoyaltyService $loyalty)
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $loyalty->customerSummary($request->user()), 'errors' => null]);
    }

    public function checkout(Request $request, LoyaltyService $loyalty)
    {
        try {
            return response()->json(['success' => true, 'message' => 'Membership checkout ready', 'data' => $loyalty->startMembershipCheckout($request->user()), 'errors' => null], 201);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'errors' => null], 409);
        }
    }

    public function verify(Request $request, LoyaltyService $loyalty)
    {
        try {
            return response()->json(['success' => true, 'message' => 'OK', 'data' => $loyalty->verifyPayment($request->user()), 'errors' => null]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Membership payment status could not be checked.', 'data' => null, 'errors' => null], 503);
        }
    }

    public function rewards()
    {
        $rewards = Reward::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('stock')->orWhere('stock', '>', 0))
            ->with('product:id,name,image')
            ->orderBy('points_cost')->orderBy('id')->get();
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rewards, 'errors' => null]);
    }

    public function redeem(Request $request, Reward $reward, LoyaltyService $loyalty)
    {
        $data = $request->validate(['idempotency_key' => 'nullable|string|min:16|max:64']);
        try {
            $redemption = $loyalty->redeem($request->user(), $reward, $data['idempotency_key'] ?? null);
            return response()->json(['success' => true, 'message' => 'Reward redeemed', 'data' => $redemption, 'errors' => null], 201);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'errors' => null], 422);
        }
    }

    public function redemptions(Request $request)
    {
        $rows = RewardRedemption::with('reward:id,name,image')
            ->where('user_id', $request->user()->id)->latest('id')->limit(100)->get();
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows, 'errors' => null]);
    }
}