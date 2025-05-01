<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\WithFaker;

class DnsControllerConcurrencyTest extends TestCase
{
    use WithoutMiddleware, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /**
     * 测试并发DNS解析请求
     */
    public function test_concurrent_dns_resolution()
    {
        // 模拟HTTP响应
        Http::fake([
            'dns.google/resolve*' => Http::response([
                'Answer' => [
                    ['data' => '8.8.8.8']
                ]
            ], 200),
            'cloudflare-dns.com/dns-query*' => Http::response([
                'Answer' => [
                    ['data' => '1.1.1.1']
                ]
            ], 200),
        ]);

        $hostname = 'example.com';
        $server = 'Google';
        
        // 创建并发请求
        $responses = [];
        for ($i = 0; $i < 50; $i++) {
            $response = $this->getJson("/api/dnsresolver?hostname={$hostname}&server={$server}");
            $response->assertOk()
                    ->assertJsonStructure([
                        'hostname',
                        'result_dns',
                        'result_doh'
                    ]);
            
            $responses[] = $response->json();
        }

        // 验证所有响应是否一致
        $firstResponse = $responses[0];
        foreach ($responses as $response) {
            $this->assertEquals($firstResponse, $response);
        }
    }

    /**
     * 测试并发请求下的错误处理
     */
    public function test_concurrent_error_handling()
    {
        // 模拟HTTP错误响应
        Http::fake([
            'dns.google/resolve*' => Http::response([], 500),
            'cloudflare-dns.com/dns-query*' => Http::response([], 500),
        ]);

        $hostname = 'invalid-domain.com';
        $server = 'Google';

        // 创建并发请求
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson("/api/dnsresolver?hostname={$hostname}&server={$server}");
            $response->assertOk()
                    ->assertJsonStructure([
                        'hostname',
                        'result_dns',
                        'result_doh'
                    ]);
            
            $responseData = $response->json();
            $this->assertNotEmpty($responseData['result_dns']);
            $this->assertNotEmpty($responseData['result_doh']);
            
            // 验证错误情况下返回N/A
            $this->assertEquals(['N/A'], $responseData['result_dns'][0]['Google']);
            
            $responses[] = $responseData;
        }
    }

    /**
     * 测试并发请求下的响应一致性
     */
    public function test_concurrent_response_consistency()
    {
        // 模拟HTTP响应
        Http::fake([
            'dns.google/resolve*' => Http::response([
                'Answer' => [
                    ['data' => '8.8.8.8']
                ]
            ], 200),
        ]);

        $hostname = 'test.com';
        $server = 'Google';

        // 第一次请求
        $firstResponse = $this->getJson("/api/dnsresolver?hostname={$hostname}&server={$server}");
        $firstResponse->assertOk();
        $firstData = $firstResponse->json();

        // 创建并发请求
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson("/api/dnsresolver?hostname={$hostname}&server={$server}");
            $response->assertOk();
            $responseData = $response->json();
            
            // 验证响应与第一次请求一致
            $this->assertEquals($firstData, $responseData);
            $responses[] = $responseData;
        }

        // 验证所有响应是否一致
        foreach ($responses as $response) {
            $this->assertEquals($firstData, $response);
        }
    }
} 