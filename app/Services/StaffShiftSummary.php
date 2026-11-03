<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\StaffShift;
use Illuminate\Support\Carbon;

class StaffShiftSummary
{
    public function calculate(StaffShift $shift, ?Carbon $closedAt = null): array
    {
        $end = $closedAt ?? now();
        $orders = $shift->orders();
        $cashSales = (float) (clone $orders)->where('payment_method', 'cash')
            ->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'rejected'])->sum('total');
        $cashRefunds = (float) (clone $orders)->where('payment_method', 'cash')
            ->whereHas('payments', fn ($query) => $query->where('method', 'cash')->where('status', 'refunded'))
            ->sum('total');

        // Informational only. Online tender never contributes to drawer cash.
        $onlinePayments = (float) Payment::query()->whereIn('method', ['qrph', 'gcash', 'card'])
            ->where('status', 'paid')->whereBetween('paid_at', [$shift->opened_at, $end])->sum('amount');

        return [
            'cash_sales' => round($cashSales, 2),
            'cash_refunds' => round($cashRefunds, 2),
            'online_payments' => round($onlinePayments, 2),
        ];
    }
}
