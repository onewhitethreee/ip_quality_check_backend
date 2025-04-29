<?php

namespace Tests\Unit;

use Illuminate\Http\Middleware\SetCacheHeaders;
use Tests\TestCase;
use App\Http\Controllers\WhoisController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Iodev\Whois\Factory;
use Iodev\Whois\Whois;
use Iodev\Whois\Modules\Tld\TldResponse;
use Iodev\Whois\Modules\Tld\TldParser;
use Iodev\Whois\Modules\Tld\DomainInfo;

class WhoisControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // 清除缓存，确保测试之间不会相互影响
        Cache::flush();
        $this->withHeaders([
            'Referer' => 'http://localhost:18966'
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试没有提供查询参数的情况
     */
    public function testNoQueryParameter(): void
    {
        $response = $this->get('/api/whois');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'No address provided']);
    }

    /**
     * 测试无效的域名
     */
    public function testInvalidDomain(): void
    {
        $response = $this->get('/api/whois?q=invalid-domain-123');  
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid IP or address']);
    }

    /**
     * 测试无效的IP地址
     */
    public function testInvalidIP(): void
    {
        $response = $this->get('/api/whois?q=999.999.999.999');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid IP or address']);
    }


    /**
     * 测试获取 Whois 服务器列表
     */
    public function testServers(): void
    {
        $response = $this->get('/api/whois/servers');
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['servers']);
    }

    
}
