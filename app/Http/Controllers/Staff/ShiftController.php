<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffShift;
use App\Models\User;
use App\Services\StaffShiftSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function show(Request $request, StaffShiftSummary $summary)
    {
        if ($request->user()->role !== 'staff') return redirect()->route('staff.orders');
        $shift = StaffShift::where('user_id', $request->user()->id)->where('status', 'open')->latest('id')->first();
        return view('staff.shifts.show', [
            'shift' => $shift,
            'summary' => $shift ? $summary->calculate($shift) : null,
            'recentClosed' => StaffShift::where('user_id', $request->user()->id)->where('status', 'closed')->latest('closed_at')->limit(5)->get(),
        ]);
    }

    public function open(Request $request)
    {
        abort_unless($request->user()->role === 'staff', 403);
        $data = $request->validate(['opening_fund' => 'required|numeric|min:0|max:99999999.99']);

        $shift = DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $active = StaffShift::where('user_id', $request->user()->id)->where('status', 'open')->lockForUpdate()->first();
            if ($active) return $active;
            return StaffShift::create([
                'user_id' => $request->user()->id,
                'status' => 'open',
                'opening_fund' => round((float) $data['opening_fund'], 2),
                'opened_at' => now(),
            ]);
        });

        if ($shift->wasRecentlyCreated) return redirect()->route('staff.orders')->with('success', 'Shift opened successfully.');
        return redirect()->route('staff.shift')->withErrors(['opening_fund' => 'You already have an open shift.']);
    }

    public function close(Request $request, StaffShift $shift, StaffShiftSummary $summary)
    {
        abort_unless($request->user()->role === 'staff' && (int) $shift->user_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'actual_cash' => 'required|numeric|min:0|max:99999999.99',
            'cash_deductions' => 'nullable|numeric|min:0|max:99999999.99',
            'deduction_reason' => 'nullable|string|max:255|required_with:cash_deductions',
        ]);

        DB::transaction(function () use ($shift, $request, $data, $summary) {
            $locked = StaffShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
            if (!$locked->isOpen()) abort(409, 'This shift has already been closed.');
            $closedAt = now();
            $totals = $summary->calculate($locked, $closedAt);
            $deductions = round((float) ($data['cash_deductions'] ?? 0), 2);
            $expected = round((float) $locked->opening_fund + $totals['cash_sales'] - $totals['cash_refunds'] - $deductions, 2);
            $actual = round((float) $data['actual_cash'], 2);
            $difference = round($actual - $expected, 2);
            $status = abs($difference) < 0.01 ? 'balanced' : ($difference < 0 ? 'shortage' : 'excess');

            $locked->update($totals + [
                'status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => $closedAt,
                'cash_deductions' => $deductions, 'deduction_reason' => $data['deduction_reason'] ?? null,
                'expected_cash' => $expected, 'actual_cash' => $actual,
                'cash_difference' => $difference, 'reconciliation_status' => $status,
            ]);
        });

        return redirect()->route('staff.shift.report', $shift)->with('success', 'Shift closed. Review or print the closing report.');
    }

    public function report(Request $request, StaffShift $shift)
    {
        abort_unless($request->user()->role === 'staff' && (int) $shift->user_id === (int) $request->user()->id, 404);
        abort_unless($shift->status === 'closed', 404);
        $shift->load('staff');
        return view('staff.shifts.report', compact('shift'));
    }
}
