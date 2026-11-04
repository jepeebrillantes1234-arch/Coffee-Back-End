<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipCardPrint;
use App\Models\RewardRedemption;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MembershipController extends Controller
{
    public function settings()
    {
        $s = Settings::all();
        $overview = [
            'members' => Membership::count(),
            'active' => Membership::where('status', 'active')->count(),
            'pending_payments' => \App\Models\MembershipPayment::where('status', 'pending')->count(),
        ];
        return view('admin.memberships.settings', compact('s', 'overview'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'membership_card_price' => 'required|numeric|min:0.01|max:100000',
            'membership_discount_percent' => 'required|numeric|min:0|max:100',
        ]);
        Settings::set('membership_card_price', number_format((float) $data['membership_card_price'], 2, '.', ''));
        Settings::set('membership_discount_percent', number_format((float) $data['membership_discount_percent'], 2, '.', ''));
        Settings::set('membership_discount_enabled', $request->boolean('membership_discount_enabled') ? '1' : '0');
        Activity::log('update', 'membership_settings', 'Updated membership price and discount settings');

        return back()->with('success', 'Membership settings saved. New prices apply to future purchases.');
    }

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
        $membership->load([
            'user',
            'payments' => fn ($q) => $q->latest('id'),
            'transactions.order:id,order_number',
            'redemptions.reward',
            'cardPrints.admin:id,name',
        ]);

        $cardReady = $membership->isActive()
            && $membership->payments->contains(fn ($payment) => $payment->status === 'paid');
        $cardPrintCount = $membership->cardPrints->count();
        $designsActive = Settings::get('membership_card_design_active', '0') === '1';
        $frontPath = Settings::get('membership_card_front_design') ?: Settings::get('membership_card_design');
        $backPath = Settings::get('membership_card_back_design');
        $frontCardUrl = $designsActive && filled($frontPath)
            ? Storage::disk('public')->url($frontPath)
            : asset('images/membership-cards/card.png');
        $backCardUrl = $designsActive && filled($backPath)
            ? Storage::disk('public')->url($backPath)
            : asset('images/membership-cards/card1.png');

        return view('admin.memberships.show', compact(
            'membership',
            'cardReady',
            'cardPrintCount',
            'frontCardUrl',
            'backCardUrl'
        ));
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

    public function markCardPrinted(Request $request, Membership $membership)
    {
        return $this->recordCardPrint($request, $membership, false);
    }

    public function reprintCard(Request $request, Membership $membership)
    {
        return $this->recordCardPrint($request, $membership, true);
    }

    private function recordCardPrint(Request $request, Membership $membership, bool $reprint)
    {
        $result = DB::transaction(function () use ($request, $membership, $reprint) {
            $locked = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $eligible = $locked->isActive()
                && $locked->payments()->where('status', 'paid')->exists();
            if (!$eligible) return 'not_ready';

            $lastPrintNumber = (int) (MembershipCardPrint::where('membership_id', $locked->id)
                ->lockForUpdate()
                ->max('print_number') ?? 0);
            if (!$reprint && $lastPrintNumber > 0) return 'already_printed';
            if ($reprint && $lastPrintNumber === 0) return 'not_printed';

            MembershipCardPrint::create([
                'membership_id' => $locked->id,
                'admin_id' => $request->user()->id,
                'print_number' => $lastPrintNumber + 1,
                'printed_at' => now(),
            ]);

            return 'recorded';
        });

        if ($result !== 'recorded') {
            $message = match ($result) {
                'not_ready' => 'Only an active membership with a verified paid payment can be printed.',
                'already_printed' => 'This card is already marked as printed. Use Reprint Card to record another copy.',
                default => 'Print the card once before recording a reprint.',
            };
            return back()->with('error', $message);
        }

        Activity::log(
            'create',
            'membership_card_prints',
            ($reprint ? 'Recorded membership card reprint for ' : 'Marked membership card printed for ') . $membership->member_id
        );

        return back()->with('success', $reprint ? 'Membership card reprint recorded.' : 'Membership card marked as printed.');
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
