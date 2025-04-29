<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\WhoisController;
use Illuminate\Http\Request;
use Iodev\Whois\Factory;
use Iodev\Whois\Modules\Tld\DomainInfo;

class WhoisControllerTest extends TestCase
{
    private $controller;
    private $mockWhois;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new WhoisController();
        
        // 创建 mock whois 对象
        $this->mockWhois = $this->createMock(\Iodev\Whois\Whois::class);
        $factory = $this->createMock(Factory::class);
        $factory->method('createWhois')->willReturn($this->mockWhois);
        
        // 使用反射设置私有属性
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('whois');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->mockWhois);
    }

    public function testInvalidDomain()
    {
        $request = new Request(['q' => 'invalid-domain']);
        $response = $this->controller->query($request);
        
        $this->assertEquals(400, $response->status());
        $this->assertJsonStringEqualsJsonString(
            '{"error":"Invalid IP or address"}',
            $response->content()
        );
    }

    public function testInvalidIP()
    {
        $request = new Request(['q' => '256.256.256.256']);
        $response = $this->controller->query($request);
        
        $this->assertEquals(400, $response->status());
        $this->assertJsonStringEqualsJsonString(
            '{"error":"Invalid IP or address"}',
            $response->content()
        );
    }

    public function testDomainQuery()
    {
        // 模拟 whois 查询结果
        $domainInfo = new DomainInfo();
        $domainInfo->text = "Domain: example.com\nRegistrar: Example Registrar";
        
        $this->mockWhois->method('lookupDomain')
            ->willReturn($domainInfo);

        $request = new Request([
            'q' => 'example.com',
            'servers' => 'whois.godaddy.com'
        ]);
        
        $response = $this->controller->query($request);
        
        $this->assertEquals(200, $response->status());
        $this->assertJsonStringEqualsJsonString(
            '{"example.com":{"whois.godaddy.com":{"__raw":"Domain: example.com\nRegistrar: Example Registrar"}}}',
            $response->content()
        );
    }

    public function testIPQuery()
    {
        // 模拟 whois 查询结果
        $domainInfo = new DomainInfo();
        $domainInfo->text = "IP: 8.8.8.8\nNetRange: 8.8.8.0 - 8.8.8.255";
        
        $this->mockWhois->method('loadDomainInfo')
            ->willReturn($domainInfo);

        $request = new Request([
            'q' => '8.8.8.8',
            'servers' => 'whois.godaddy.com'
        ]);
        
        $response = $this->controller->query($request);
        
        $this->assertEquals(200, $response->status());
        $this->assertJsonStringEqualsJsonString(
            '{"whois.godaddy.com":{"__raw":"IP: 8.8.8.8\nNetRange: 8.8.8.0 - 8.8.8.255"}}',
            $response->content()
        );
    }

    public function testNoQueryParameter()
    {
        $request = new Request();
        $response = $this->controller->query($request);
        
        $this->assertEquals(400, $response->status());
        $this->assertJsonStringEqualsJsonString(
            '{"error":"No address provided"}',
            $response->content()
        );
    }

    public function testMultipleServers()
    {
        // 模拟多个服务器的查询结果
        $domainInfo1 = new DomainInfo();
        $domainInfo1->text = "Server 1 result";
        
        $domainInfo2 = new DomainInfo();
        $domainInfo2->text = "Server 2 result";
        
        $this->mockWhois->method('lookupDomain')
            ->willReturnOnConsecutiveCalls($domainInfo1, $domainInfo2);

        $request = new Request([
            'q' => 'example.com',
            'servers' => 'whois.godaddy.com,whois.iana.org'
        ]);
        
        $response = $this->controller->query($request);
        
        $this->assertEquals(200, $response->status());
        $this->assertJsonStringEqualsJsonString(
            '{"example.com":{"whois.godaddy.com":{"__raw":"Server 1 result"},"whois.iana.org":{"__raw":"Server 2 result"}}}',
            $response->content()
        );
    }

    public function testInvalidReferer()
    {
        $request = new Request(['q' => 'example.com']);
        $request->headers->set('referer', 'http://invalid-domain.com');
        
        $response = $this->controller->query($request);
        
        $this->assertEquals(403, $response->status());
        $this->assertJsonStringEqualsJsonString(
            '{"error":"Access denied"}',
            $response->content()
        );
    }
} 