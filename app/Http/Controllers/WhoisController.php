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
        $allowedDomains = [
            'localhost',
        ];
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

        // 验证输入是否为有效的IP或域名
        if (!$this->isValidIP($query) && !$this->isValidDomain($query)) {
            return response()->json(['error' => 'Invalid IP or address'], 400);
        }

        // 创建WHOIS查询实例
        $whois = Factory::get()->createWhois();

        try {
            if ($this->isValidIP($query)) {
                // 查询IP信息
                $info = $whois->loadDomainInfo($query);
                if (!$info) {
                    return response()->json(['error' => 'No data found for this IP'], 404);
                }

                return response()->json($info->toArray());
            } else {
                // 查询域名信息
                $info = $whois->lookupDomain($query);
                if (!$info) {
                    return response()->json(['error' => 'No data found for this domain'], 404);
                }

                return response()->json($info->toArray());
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
