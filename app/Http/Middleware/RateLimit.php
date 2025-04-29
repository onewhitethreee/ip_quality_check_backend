<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class RateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $key = $request->ip();
        $maxAttempts = config('app.rate_limit', 100);
        $decayMinutes = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            // 记录被限流的IP
            Log::warning('Rate limit exceeded', [
                'ip' => $key,
                'attempts' => RateLimiter::attempts($key),
                'remaining' => RateLimiter::remaining($key, $maxAttempts)
            ]);

            return response()->json(['message' => 'Too Many Requests'], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }
} 