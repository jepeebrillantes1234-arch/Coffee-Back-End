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
        $data = $request->validate([
            'idempotency_key' => 'required|string|min:16|max:64',
            'fulfillment_type' => 'nullable|in:delivery,pickup',
            'address_id' => 'required_if:fulfillment_type,delivery|nullable|integer',
        ]);
        try {
            $redemption = $loyalty->redeem($request->user(), $reward, $data['idempotency_key'], $data['fulfillment_type'] ?? 'pickup', isset($data['address_id']) ? (int) $data['address_id'] : null);
            $redemption->order_number = $redemption->order?->order_number;
            $redemption->order_status = $redemption->order?->status;
            return response()->json(['success' => true, 'message' => 'Reward redeemed', 'data' => $redemption, 'errors' => null], 201);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'errors' => null], 422);
        }
    }

    public function redemptions(Request $request)
    {
        $rows = RewardRedemption::with(['reward:id,name,image', 'order:id,order_number,status,delivery_fee'])
            ->where('user_id', $request->user()->id)->latest('id')->limit(100)->get()
            ->each(function ($row) { $row->order_number = $row->order?->order_number; $row->order_status = $row->order?->status; });
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows, 'errors' => null]);
    }

    public function deliveryQuote(Request $request, Reward $reward, LoyaltyService $loyalty)
    {
        $data = $request->validate(['address_id' => 'required|integer']);
        try {
            return response()->json(['success' => true, 'message' => 'OK', 'data' => $loyalty->deliveryQuote($request->user(), $reward, (int) $data['address_id']), 'errors' => null]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'errors' => null], 422);
        }
    }

    public function cancelRedemption(Request $request, RewardRedemption $redemption, LoyaltyService $loyalty)
    {
        try {
            $row = $loyalty->cancelRedemption($request->user(), $redemption);
            return response()->json(['success' => true, 'message' => 'Redemption cancelled', 'data' => $row, 'errors' => null]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'errors' => null], 409);
        }
    }
}
