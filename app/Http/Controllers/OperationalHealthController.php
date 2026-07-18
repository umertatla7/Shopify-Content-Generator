<?php

namespace App\Http\Controllers;

use App\Services\OperationalHealthService;
use Illuminate\Http\JsonResponse;

class OperationalHealthController extends Controller
{
    public function __invoke(OperationalHealthService $health): JsonResponse
    {
        $result = $health->inspect();
        $token = (string) config('operations.health_token');
        $authorized = $token !== '' && hash_equals($token, (string) request()->header('X-Health-Token'));

        if (! $authorized) {
            unset($result['checks']);
        }

        return response()->json($result, $result['status'] === 'ok' ? 200 : 503);
    }
}
