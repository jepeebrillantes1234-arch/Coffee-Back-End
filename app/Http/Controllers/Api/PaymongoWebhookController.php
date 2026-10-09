<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymongoWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gw)
    {
        $raw = $request->getContent();

        if (!$gw->verifySignature($raw, $request->header('Paymongo-Signature'))) {
            Log::warning('PayMongo webhook: maling signature');
            return response()->json(['ok' => false], 401);
        }

        try {
            $gw->handleEvent($request->json()->all());
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false], 500);   // para mag-retry ang PayMongo
        }
        return response()->json(['ok' => true]);
    }
}
