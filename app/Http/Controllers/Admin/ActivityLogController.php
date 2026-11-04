<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'role' => 'nullable|in:staff,rider,admin',
            'action' => 'nullable|string|max:50',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        $logs = ActivityLog::with('user:id,name,role')
            ->when($request->module, fn ($q, $v) => $q->where('module', $v))
            ->when($request->search, fn ($q, $s) => $q->where('description', 'like', "%$s%"))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->whereHas('user', fn ($user) => $user->where('role', $role)))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        $modules = DB::table('activity_logs')->distinct()->orderBy('module')->pluck('module');
        $actions = DB::table('activity_logs')->distinct()->orderBy('action')->pluck('action');
        $users = User::whereIn('role', ['admin', 'staff', 'rider'])->orderBy('name')->get(['id', 'name', 'role']);

        return view('admin.activity.index', compact('logs', 'modules', 'actions', 'users'));
    }
}
