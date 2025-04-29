<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefererCheckMiddleware
{
    /**
     * 处理传入请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 允许的来源域名列表
        $allowedDomains = config('app.allowed_referers', ['localhost', '127.0.0.1', 'localhost:8000']);
        // if (app()->environment('local')) {
        //     return $next($request);
        // }
        $referer = $request->header('referer');
        
        // 添加详细的调试日志
        // Log::info('Referer Check Debug:', [
        //     'raw_referer' => $referer,
        //     'parsed_referer' => [
        //         'host' => parse_url($referer, PHP_URL_HOST),
        //         'port' => parse_url($referer, PHP_URL_PORT),
        //         'scheme' => parse_url($referer, PHP_URL_SCHEME)
        //     ],
        //     'raw_allowed_domains' => $allowedDomains,
        //     'cleaned_domains' => array_map(function($domain) {
        //         $parsed = parse_url($domain);
        //         $host = $parsed['host'] ?? $domain;
        //         $port = $parsed['port'] ?? null;
        //         return $host . ($port ? ':' . $port : '');
        //     }, $allowedDomains),
        //     'env_allowed_referers' => env('ALLOWED_REFERERS'),
        //     'config_allowed_referers' => config('app.allowed_referers')
        // ]);

        if (!$referer) {
            return response()->json(['error' => 'What are you doing?'], 403);
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        $refererPort = parse_url($referer, PHP_URL_PORT);
        $refererHostWithPort = $refererHost . ($refererPort ? ':' . $refererPort : '');

        // 清理 allowedDomains 中的协议部分，保留端口
        $cleanedDomains = array_map(function($domain) {
            $parsed = parse_url($domain);
            $host = $parsed['host'] ?? $domain;
            $port = $parsed['port'] ?? null;
            return $host . ($port ? ':' . $port : '');
        }, $allowedDomains);

        foreach ($cleanedDomains as $domain) {
            if (
                $refererHostWithPort === $domain ||
                (strpos($domain, '*') === 0 && substr($refererHostWithPort, -strlen(substr($domain, 1))) === substr($domain, 1))
            ) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'Access denied'], 403);
    }
}