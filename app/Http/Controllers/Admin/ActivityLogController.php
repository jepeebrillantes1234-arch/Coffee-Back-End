<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user:id,name,role')
            ->when($request->module, fn ($q, $v) => $q->where('module', $v))
            ->when($request->search, fn ($q, $s) => $q->where('description', 'like', "%$s%"))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        $modules = DB::table('activity_logs')->distinct()->orderBy('module')->pluck('module');

        return view('admin.activity.index', compact('logs', 'modules'));
    }
}
