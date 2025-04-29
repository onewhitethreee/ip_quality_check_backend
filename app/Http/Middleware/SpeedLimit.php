<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SpeedLimit
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
        $delayAfter = config('app.delay_after', 50);
        $decayMinutes = 60;

        $attempts = RateLimiter::attempts($key);
        
        if ($attempts > $delayAfter) {
            // 计算延迟时间（毫秒）
            $delayMs = ($attempts - $delayAfter) * 400;
            usleep($delayMs * 1000); // 转换为微秒
        }

        return $next($request);
    }
} 