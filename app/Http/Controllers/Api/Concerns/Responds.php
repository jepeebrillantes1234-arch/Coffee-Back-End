<?php
namespace App\Http\Controllers\Api\Concerns;

trait Responds
{
    protected function ok($data = null, string $msg = 'OK', int $code = 200)
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data, 'errors' => null], $code);
    }

    protected function fail(string $msg, int $code = 422)
    {
        return response()->json(['success' => false, 'message' => $msg, 'data' => null, 'errors' => null], $code);
    }
}
