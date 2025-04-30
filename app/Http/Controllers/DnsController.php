<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DnsController extends Controller
{
    // DNS服务器映射到其DoH服务
    private $dnsToDoHMap = [
        'Google' => 'https://dns.google/resolve?',
        'Cloudflare' => 'https://cloudflare-dns.com/dns-query?ct=application/dns-json&',
        'AdGuard' => 'https://dns.adguard.com/resolve?',
        'AliDNS' => 'https://dns.alidns.com/resolve?',
        // 添加更多映射
    ];

    // 普通DNS服务器列表
    private $dnsServers = [
        'Google' => '8.8.8.8',
        'Cloudflare' => '1.1.1.1',
        'OpenDNS' => '208.67.222.222',
        'Quad9' => '9.9.9.9',
        'ControlD' => '76.76.2.0',
        'AdGuard' => '94.140.14.14',
        'Quad 101' => '101.101.101.101',
        'AliDNS' => '223.5.5.5',
        'DNSPod' => '119.29.29.29',
        '114DNS' => '114.114.114.114',
        'China Unicom' => '123.123.123.123',
    ];

    // DNS-over-HTTPS服务列表
    private $dohServers = [
        'Google' => 'https://dns.google/resolve?',
        'Cloudflare' => 'https://cloudflare-dns.com/dns-query?ct=application/dns-json&',
        'AdGuard' => 'https://dns.adguard.com/resolve?',
        'AliDNS' => 'https://dns.alidns.com/resolve?',
    ];

    /**
     * 处理DNS解析请求
     */
    public function resolve(Request $request)
    {
        // 获取参数
        $hostname = $request->query('hostname');
        $type = $request->query('type', 'A');
        $server = $request->query('server');

        // 参数验证
        if (empty($hostname)) {
            return response()->json(['error' => 'Missing hostname parameter'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/', $hostname)) {
            return response()->json(['error' => 'Invalid hostname'], 400);
        }

        if (empty($server)) {
            return response()->json(['error' => 'No server specified'], 403);
        }

        if (!isset($this->dnsServers[$server]) && !isset($this->dohServers[$server])) {
            return response()->json(['error' => 'Invalid DNS server specified'], 400);
        }

        // 使用缓存避免重复查询
        $cacheKey = "dns:{$hostname}:{$type}:{$server}";
        $cacheTTL = 600; // 缓存10分钟

        // 先检查缓存
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        // 初始化结果数组
        $result_dns = [];
        $result_doh = [];

        // 执行DoH查询（如果服务器支持）
        if (isset($this->dohServers[$server])) {
            $result = $this->resolveDoh($hostname, $type, $server, $this->dohServers[$server]);
            $result_doh[] = $result;
        }

        // 始终执行传统DNS查询（无论DoH是否成功）
        if (isset($this->dnsServers[$server])) {
            $result = $this->resolveDns($hostname, $type, $server, $this->dnsServers[$server]);
            $result_dns[] = $result;
        }

        $response = [
            'hostname' => $hostname,
            'result_dns' => $result_dns,
            'result_doh' => $result_doh
        ];

        // 存入缓存
        Cache::put($cacheKey, $response, $cacheTTL);

        return response()->json($response);
    }

    /**
     * 使用传统DNS服务器查询（在不能使用exec的情况下）
     */
    private function resolveDns($hostname, $type, $name, $server)
    {
        try {
            // 根据DNS记录类型设置DNS查询类型
            $recordType = match ($type) {
                'A' => DNS_A,
                'AAAA' => DNS_AAAA,
                'CNAME' => DNS_CNAME,
                'MX' => DNS_MX,
                'NS' => DNS_NS,
                'TXT' => DNS_TXT,
                default => DNS_A,
            };

            // 使用PHP原生函数进行DNS查询
            // 注意：这不能保证使用指定的DNS服务器，但是在无法使用exec的情况下是一种替代方案
            $result = [];

            // 获取系统默认解析结果
            $addresses = dns_get_record($hostname, $recordType);

            if (empty($addresses)) {
                return [$name => ['N/A']];
            }

            // 处理不同记录类型的结果
            foreach ($addresses as $record) {
                if ($type == 'A' && isset($record['ip'])) {
                    $result[] = $record['ip'];
                } elseif ($type == 'AAAA' && isset($record['ipv6'])) {
                    $result[] = $record['ipv6'];
                } elseif ($type == 'CNAME' && isset($record['target'])) {
                    $result[] = $record['target'];
                } elseif ($type == 'MX' && isset($record['target'])) {
                    $result[] = $record['pri'] . ' ' . $record['target'];
                } elseif ($type == 'NS' && isset($record['target'])) {
                    $result[] = $record['target'];
                } elseif ($type == 'TXT' && isset($record['txt'])) {
                    $result[] = $record['txt'];
                }
            }

            if (empty($result)) {
                return [$name => ['N/A']];
            }

            return [$name => $result];
        } catch (\Exception $e) {
            Log::error('DNS解析错误: ' . $e->getMessage(), ['exception' => $e]);
            return [$name => ['N/A']];
        }
    }

    /**
     * 使用DNS-over-HTTPS服务查询
     */
    private function resolveDoh($hostname, $type, $name, $url)
    {
        try {
            // 设置低超时时间提高响应速度
            $response = Http::timeout(1)
                ->connectTimeout(0.5)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get($url . "name={$hostname}&type={$type}");

            if (!$response->successful()) {
                return [$name => ['N/A']];
            }

            $data = $response->json();

            // 处理不同类型的DoH响应格式
            if (isset($data['Answer'])) {
                $addresses = array_map(function ($answer) {
                    return $answer['data'];
                }, $data['Answer']);
            } else {
                $addresses = ['N/A'];
            }

            if (empty($addresses)) {
                return [$name => ['N/A']];
            }

            return [$name => $addresses];
        } catch (\Exception $e) {
            Log::error('DoH解析错误: ' . $e->getMessage(), [
                'exception' => $e,
                'url' => $url,
                'hostname' => $hostname
            ]);
            return [$name => ['N/A']];
        }
    }
}