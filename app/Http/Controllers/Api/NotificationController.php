<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    use Responds;

    public function index(Request $request)
    {
        $uid = $request->user()->id;

        $items = DB::table('app_notifications')->where('user_id', $uid)->orderByDesc('id')->limit(50)->get()
            ->map(fn ($n) => [
                'id' => $n->id, 'title' => $n->title, 'message' => $n->message, 'type' => $n->type,
                'data' => json_decode($n->data ?? 'null', true),
                'is_read' => (bool) $n->is_read,
                'created_at' => Carbon::parse($n->created_at)->toIso8601String(),
            ])->values();

        $unread = DB::table('app_notifications')->where('user_id', $uid)->where('is_read', false)->count();

        return $this->ok(['items' => $items, 'unread' => $unread]);
    }

    public function readAll(Request $request)
    {
        DB::table('app_notifications')->where('user_id', $request->user()->id)->where('is_read', false)
            ->update(['is_read' => true, 'updated_at' => now()]);
        return $this->ok(null, 'OK');
    }
}
