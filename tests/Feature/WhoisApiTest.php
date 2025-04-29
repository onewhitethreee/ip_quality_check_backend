<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;

class WhoisApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // 清除缓存，确保测试之间不会相互影响
        Cache::flush();
        
        // 设置允许的域名
        Config::set('app.allowed_referers', ['example.com', 'localhost', '127.0.0.1']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试 Whois API 端点 - 没有 Referer 头
     */
    public function testWhoisApiWithoutReferer(): void
    {
        $response = $this->get('/api/whois?q=example.com');
        
        $response->assertStatus(403)
                 ->assertJson(['error' => 'What are you doing?']);
    }

    /**
     * 测试 Whois API 端点 - 无效的 Referer 头
     */
    public function testWhoisApiWithInvalidReferer(): void
    {
        $response = $this->get('/api/whois?q=example.com', ['Referer' => 'http://invalid-domain.com']);
        
        $response->assertStatus(403)
                 ->assertJson(['error' => 'Access denied']);
    }

    /**
     * 测试 Whois API 端点 - 有效的 Referer 头但无效的查询
     */
    public function testWhoisApiWithValidRefererButInvalidQuery(): void
    {
        $response = $this->get('/api/whois?q=invalid-domain-123', ['Referer' => 'http://localhost']);
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid IP or address']);
    }

    /**
     * 测试 Whois 服务器列表 API 端点
     */
    public function testWhoisServersApi(): void
    {
        $response = $this->get('/api/whois/servers', ['Referer' => 'http://localhost']);
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['servers']);
    }

    
}
