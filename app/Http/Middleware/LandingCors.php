<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly final class LandingCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $origin = $request->headers->get('Origin');

        if ($origin !== null && in_array($origin, config('landing.allowed_origins', []), true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
