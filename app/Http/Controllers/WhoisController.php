<?php

namespace App\Http\Controllers;
use Iodev\Whois\Factory;

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
    /*
     * 检查来源请求是否合法
     * 
     * @param string $domain
     * @return bool
     */
    private function refererCheck($referer)
    {
        // 允许的来源域名列表
        $allowedDomains = config('app.allowed_referers');
        // 本地环境允许所有来源
        if (app()->environment('local')) {
            return true;
        }

        if (!$referer) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);

        foreach ($allowedDomains as $domain) {
            if (
                $refererHost === $domain ||
                (strpos($domain, '*') === 0 && substr($refererHost, -strlen(substr($domain, 1))) === substr($domain, 1))
            ) {
                return true;
            }
        }

        return false;
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
        // 验证请求来源
        $referer = $request->header('referer');
        if (!$this->refererCheck($referer)) {
            return response()->json(['error' => $referer ? 'Access denied' : 'What are you doing?'], 403);
        }

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
                    return response()->json(['error' => 'No data found for this IP'], 404);
                }

                return response()->json($results);
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
                    return response()->json(['error' => 'No data found for this domain'], 404);
                }

                return response()->json([
                    $query => $results
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
