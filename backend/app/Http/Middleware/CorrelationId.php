<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = (string) $request->header('X-Correlation-ID', '');
        $id = preg_match('/^[A-Za-z0-9._-]{1,80}$/', $candidate) ? $candidate : (string) Str::uuid();
        Log::withContext(['correlation_id' => $id]);
        try {
            $response = $next($request);
            $response->headers->set('X-Correlation-ID', $id);

            return $response;
        } finally {
            Log::withoutContext();
        }
    }
}
