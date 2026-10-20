<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['android', 'ios', 'iOS'])],
        ]);

        $request->user()->forceFill([
            'fcm_token' => $data['token'],
            'fcm_platform' => strtolower($data['platform']),
        ])->save();

        return response()->json(['message' => 'Device registered.']);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if ($user->fcm_token === $data['token']) {
            $user->forceFill([
                'fcm_token' => null,
                'fcm_platform' => null,
            ])->save();
        }

        return response()->noContent();
    }
}