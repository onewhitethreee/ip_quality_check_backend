<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SpeedLimit
{
    /**
     * 控制请求速度
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

        // 获取当前尝试次数
        $attempts = RateLimiter::attempts($key);

        // 如果超过阈值，添加延迟
        if ($attempts > $delayAfter) {
            $delayMs = ($attempts - $delayAfter) * 400;
            usleep($delayMs * 1000); 
        }

        // 记录本次请求并设置衰减时间
        RateLimiter::hit($key, $decayMinutes);

        // 继续处理请求
        return $next($request);
    }
} 