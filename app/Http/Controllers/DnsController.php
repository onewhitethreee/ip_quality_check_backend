<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DnsController extends Controller
{
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
        // 获取和验证参数
        $hostname = $request->query('hostname');
        $type = $request->query('type', 'A');
        $server = $request->query('server');

        if (empty($hostname)) {
            return response()->json(['error' => 'Missing hostname parameter'], 400);
        }

        if (!str_contains($hostname, '.')) {
            return response()->json(['error' => 'Invalid hostname'], 400);
        }

        // 收集所有查询任务
        $result_dns = [];
        $result_doh = [];

        // 传统DNS查询
        if ($server) {
            // 如果指定了服务器，只查询该服务器
            if (isset($this->dnsServers[$server])) {
                $result = $this->resolveDns($hostname, $type, $server, $this->dnsServers[$server]);
                $result_dns[] = $result;
            } else {
                return response()->json(['error' => 'Invalid DNS server specified'], 400);
            }
        } else {
            // // 否则查询所有服务器
            // foreach ($this->dnsServers as $name => $ip) {
            //     $result = $this->resolveDns($hostname, $type, $name, $ip);
            //     $result_dns[] = $result;
            // }
            return response()->json(['error' => 'No server specified'], 403);
        }

        // DNS-over-HTTPS查询
        if ($server) {
            // 如果指定了服务器，只查询该服务器
            if (isset($this->dohServers[$server])) {
                $result = $this->resolveDoh($hostname, $type, $server, $this->dohServers[$server]);
                $result_doh[] = $result;
            }
        } else {
            // 否则查询所有服务器
            return response()->json(['error' => 'No DNS server specified'], 403);
        }

        return response()->json([
            'hostname' => $hostname,
            'result_dns' => $result_dns,
            'result_doh' => $result_doh
        ]);
    }

    /**
     * 使用传统DNS服务器查询
     */
    private function resolveDns($hostname, $type, $name, $server)
    {
        try {
            // 设置DNS服务器
            putenv("RES_NAMESERVERS={$server}");

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

            // Log::info("Querying DNS for {$hostname} with type {$type} using server {$server}");

            // 使用PHP内置函数进行DNS查询
            $addresses = dns_get_record($hostname, $recordType);

            if (empty($addresses)) {
                Log::warning("No DNS records found for {$hostname}");
                return [$name => ['N/A']];
            }

            // 处理不同记录类型的结果
            $result = [];
            foreach ($addresses as $record) {
                if ($type == 'A' && isset($record['ip'])) {
                    $result[] = $record['ip'];
                } elseif ($type == 'AAAA' && isset($record['ipv6'])) {
                    $result[] = $record['ipv6'];
                } elseif ($type == 'CNAME' && isset($record['target'])) {
                    $result[] = $record['target'];
                } elseif ($type == 'MX' && isset($record['target'])) {
                    $result[] = $record['pri'] . ' ' . $record['target'] . '.';
                } elseif ($type == 'NS' && isset($record['target'])) {
                    $result[] = $record['target'];
                } elseif ($type == 'TXT' && isset($record['txt'])) {
                    $result[] = $record['txt'];
                }
            }

            Log::info("DNS query result: " . json_encode($result));
            return [$name => empty($result) ? ['N/A'] : $result];
        } catch (\Exception $e) {
            Log::error('DNS解析错误: ' . $e->getMessage());
            return [$name => ['N/A']];
        }
    }

    /**
     * 使用DNS-over-HTTPS服务查询
     */
    private function resolveDoh($hostname, $type, $name, $url)
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get($url . "name={$hostname}&type={$type}");

            $data = $response->json();
            $addresses = isset($data['Answer']) ? array_map(function ($answer) {
                return $answer['data'];
            }, $data['Answer']) : ['N/A'];

            if (empty($addresses)) {
                return [$name => ['N/A']];
            }

            return [$name => $addresses];
        } catch (\Exception $e) {
            Log::error('DoH解析错误: ' . $e->getMessage());
            return [$name => ['N/A']];
        }
    }

}