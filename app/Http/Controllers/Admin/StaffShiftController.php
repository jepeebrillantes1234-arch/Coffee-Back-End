<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffShift;
use Illuminate\Http\Request;

class StaffShiftController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'status' => 'nullable|in:open,closed',
        ]);
        $query = StaffShift::with('staff:id,name,email')->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('staff', fn ($staff) => $staff->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('opened_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('opened_at', '<=', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $counts = [
            'open' => StaffShift::where('status', 'open')->count(),
            'closed' => StaffShift::where('status', 'closed')->count(),
            'shortage' => StaffShift::where('reconciliation_status', 'shortage')->count(),
            'excess' => StaffShift::where('reconciliation_status', 'excess')->count(),
        ];
        return view('admin.shifts.index', ['shifts' => $query->latest('opened_at')->paginate(20)->withQueryString(), 'counts' => $counts, 'filters' => $filters]);
    }

    public function report(StaffShift $shift)
    {
        $shift->load('staff:id,name,email', 'closedBy:id,name');
        return view('admin.shifts.report', compact('shift'));
    }
}
