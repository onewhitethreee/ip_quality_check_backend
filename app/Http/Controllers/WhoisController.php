<?php

namespace App\Http\Controllers;
use Iodev\Whois\Factory;
use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;

class WhoisController extends Controller
{
    /*
     * 检查域名是否有效
     * 
     * @param string $domain
     * @return bool
     */
    private function isValidDomain($domain)
    {
        $domainPattern = '/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i';
        return preg_match($domainPattern, $domain);
    }

    /*
     * 检查IP地址是否有效
     * 
     * @param string $ip
     * @return bool
     */
    private function isValidIP($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP) != false;
    }


    private function getWhoisServers()
    {
        return [
            'whois.godaddy.com',
            'whois.iana.org',
            'whois.arin.net',
            'whois.apnic.net',
            'whois.ripe.net',
            'whois.lacnic.net',
            'whois.afrinic.net'
        ];
    }

    private function queryWhoisServer($query, $server)
    {
        try {
            $whois = Factory::get()->createWhois();

            if ($this->isValidIP($query)) {
                return $whois->loadDomainInfo($query);
            } else {
                return $whois->lookupDomain($query);
            }
        } catch (\Exception $e) {
            return null;
        }
    }
    /*
     * 获取Whois服务器列表
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function servers()
    {
        return response()->json([
            'servers' => $this->getWhoisServers()
        ]);
    }
    public function query(Request $request)
    {
        // 获取查询参数
        $query = $request->query('q');
        if (!$query) {
            return response()->json(['error' => 'No address provided'], 400);
        }

        // 获取选择的服务器
        $selectedServers = $request->query('servers');
        $servers = $selectedServers ? explode(',', $selectedServers) : ['whois.godaddy.com'];

        // 验证输入是否为有效的IP或域名
        if (!$this->isValidIP($query) && !$this->isValidDomain($query)) {
            return response()->json(['error' => 'Invalid IP or address'], 400);
        }

        // 生成缓存键
        $cacheKey = 'whois_' . md5($query . '_' . implode(',', $servers));

        // 先尝试从缓存获取数据
        $data = Cache::remember($cacheKey, 60 * 60 * 24, function () use ($query, $servers) {
            try {
                if ($this->isValidIP($query)) {
                    // IP 查询 - 从多个服务器获取信息
                    $results = [];
                    foreach ($servers as $server) {
                        $info = $this->queryWhoisServer($query, $server);
                        if ($info && $info->text) {
                            $results[$server] = [
                                '__raw' => $info->text
                            ];
                        }
                    }

                    if (empty($results)) {
                        // 不要返回response对象，而是返回带有错误标识的数据
                        return ['__error' => 'No data found for this IP', '__code' => 404];
                    }

                    return $results;
                } else {
                    // 域名查询 - 从多个服务器获取信息
                    $results = [];
                    foreach ($servers as $server) {
                        $info = $this->queryWhoisServer($query, $server);
                        if ($info && $info->text) {
                            $results[$server] = [
                                '__raw' => $info->text
                            ];
                        }
                    }

                    if (empty($results)) {
                        // 不要返回response对象，而是返回带有错误标识的数据
                        return ['__error' => 'No data found for this domain', '__code' => 404];
                    }

                    return [
                        $query => $results
                    ];
                }
            } catch (\Exception $e) {
                return ['__error' => $e->getMessage(), '__code' => 500];
            }
        });

        // 检查返回的数据是否包含错误
        if (isset($data['__error'])) {
            // 从缓存数据中提取错误信息和状态码
            $errorMessage = $data['__error'];
            $errorCode = $data['__code'] ?? 500;
            return response()->json(['error' => $errorMessage], $errorCode);
        }

        // 返回正常数据，并添加缓存控制头
        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Expires', gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    }
}