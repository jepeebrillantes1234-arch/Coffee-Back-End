<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

class SupportContactController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'name' => Settings::get('support_name', 'KJM Coffee Shop Customer Service'),
                'phone' => Settings::get('support_phone', ''),
                'email' => Settings::get('support_email', ''),
                'whatsapp' => Settings::get('support_whatsapp', ''),
                'hours' => Settings::get('support_hours', ''),
            ],
        ]);
    }
}
