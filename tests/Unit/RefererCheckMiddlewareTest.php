<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\RefererCheckMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

class RefererCheckMiddlewareTest extends TestCase
{
    protected $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RefererCheckMiddleware();
    }

    /**
     * 测试没有 Referer 头的请求
     */
    public function testNoReferer(): void
    {
        $request = new Request();
        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('{"error":"What are you doing?"}', $response->getContent());
    }

    /**
     * 测试无效的 Referer 头
     */
    public function testInvalidReferer(): void
    {
        // 设置允许的域名
        Config::set('app.allowed_referers', ['example.com', 'localhost:8000']);
        
        $request = new Request();
        $request->headers->set('referer', 'http://invalid-domain.com');
        
        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('{"error":"Access denied"}', $response->getContent());
    }

    /**
     * 测试有效的 Referer 头
     */
    public function testValidReferer(): void
    {
        // 设置允许的域名
        Config::set('app.allowed_referers', ['example.com', 'localhost:8000']);
        
        $request = new Request();
        $request->headers->set('referer', 'http://example.com/page');
        
        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * 测试带端口的 Referer 头
     */
    public function testRefererWithPort(): void
    {
        // 设置允许的域名
        Config::set('app.allowed_referers', ['example.com', 'localhost:8000']);
        
        $request = new Request();
        $request->headers->set('referer', 'http://localhost:8000/page');
        
        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * 测试通配符域名
     */
    public function testWildcardDomain(): void
    {
        // 设置允许的域名，包括通配符
        Config::set('app.allowed_referers', ['*.example.com']);
        
        $request = new Request();
        $request->headers->set('referer', 'http://subdomain.example.com/page');
        
        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * 测试本地环境
     */
    public function testLocalEnvironment(): void
    {
        // 设置应用环境为本地
        app()['env'] = 'local';
        
        // 取消注释 RefererCheckMiddleware 中的本地环境检查代码
        // 注意：这需要修改 RefererCheckMiddleware 类，取消注释第22-24行
        // if (app()->environment('local')) {
        //     return $next($request);
        // }
        
        $request = new Request();
        // 不设置 Referer 头
        
        $next = function ($request) {
            return new Response('OK');
        };

        // 如果本地环境检查代码被取消注释，这个测试将通过
        // 否则，它将失败
        $response = $this->middleware->handle($request, $next);
        
        // 由于代码被注释，预期会返回403
        $this->assertEquals(403, $response->getStatusCode());
        
        // 如果取消注释了本地环境检查代码，应该修改为：
        // $this->assertEquals(200, $response->getStatusCode());
        // $this->assertEquals('OK', $response->getContent());
    }

    /**
     * 测试集成 - 通过路由
     */
    public function testMiddlewareIntegration(): void
    {
        // 设置允许的域名
        Config::set('app.allowed_referers', ['example.com', 'localhost', '127.0.0.1']);
        
        // 发送带有有效 Referer 的请求
        $response = $this->get('/api/whois/servers', ['Referer' => 'http://localhost']);
        
        // 应该成功
        $response->assertStatus(200);
        
        // 发送带有无效 Referer 的请求
        $response = $this->get('/api/whois/servers', ['Referer' => 'http://invalid-domain.com']);
        
        // 应该失败
        $response->assertStatus(403);
    }
}
