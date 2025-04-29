<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\MacController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Mockery;

class MacControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // 清除缓存
        Cache::flush();
        
        // 设置测试请求头
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
     * 测试无效的MAC地址
     */
    public function testInvalidMacAddress(): void
    {
        $response = $this->get('/api/mac?q=invalid-mac');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid MAC address']);
    }

    /**
     * 测试没有提供MAC地址
     */
    public function testNoMacAddressProvided(): void
    {
        $response = $this->get('/api/mac');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'No MAC address provided']);
    }

    /**
     * 测试获取MAC服务器列表
     */
    public function testGetServers(): void
    {
        $response = $this->get('/api/mac/servers');
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['servers']);
    }

    /**
     * 测试成功查询 maclookup.app
     */
    public function testSuccessfulMacLookupQuery(): void
    {
        $testMac = '00:11:22:33:44:55';
        $mockResponse = [
            'success' => true,
            'macPrefix' => '001122',
            'company' => 'Test Company',
            'country' => 'Test Country',
            'address' => 'Test Address',
            'blockStart' => '001122000000',
            'blockEnd' => '001122FFFFFF',
            'blockSize' => '16777216',
            'blockType' => 'MA-L',
            'updated' => '2023-01-01'
        ];

        Http::fake([
            'https://api.maclookup.app/v2/macs/*' => Http::response($mockResponse, 200)
        ]);

        $response = $this->get('/api/mac?q=' . $testMac);
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'api.maclookup.app/v2/macs' => [
                         'success',
                         'macPrefix',
                         'company',
                         'country',
                         'address',
                         'blockStart',
                         'blockEnd',
                         'blockSize',
                         'blockType',
                         'updated',
                         'isMulticast',
                         'isLocal',
                         'isGlobal',
                         'isUnicast',
                         'source'
                     ]
                 ]);
    }

    /**
     * 测试成功查询 macvendors.com
     */
    public function testSuccessfulMacVendorsQuery(): void
    {
        $testMac = '00:11:22:33:44:55';
        $mockCompany = 'Test Vendor Company';

        Http::fake([
            'https://api.macvendors.com/*' => Http::response($mockCompany, 200)
        ]);

        $response = $this->get('/api/mac?q=' . $testMac . '&servers=api.macvendors.com');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'api.macvendors.com' => [
                         'success',
                         'macPrefix',
                         'company',
                         'country',
                         'address',
                         'blockStart',
                         'blockEnd',
                         'blockSize',
                         'blockType',
                         'updated',
                         'isMulticast',
                         'isLocal',
                         'isGlobal',
                         'isUnicast',
                         'source'
                     ]
                 ]);

        $response->assertJson([
            'api.macvendors.com' => [
                'company' => $mockCompany,
                'source' => 'macvendors.com'
            ]
        ]);
    }

    /**
     * 测试API请求失败的情况
     */
    public function testFailedApiRequest(): void
    {
        Http::fake([
            '*' => Http::response(null, 500)
        ]);

        $response = $this->get('/api/mac?q=00:11:22:33:44:55');
        
        $response->assertStatus(404)
                 ->assertJson(['error' => 'No data found for this MAC address']);
    }

    
}