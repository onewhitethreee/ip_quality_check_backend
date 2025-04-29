<?php

namespace App\Http\Controllers;

use Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MacController extends Controller
{
    /*
     * 检查MAC地址是否有效
     * 
     * @param string $mac
     * @return bool
     */
    private function isValidMAC($address)
    {
        $normalizedAddress = preg_replace('/[:-]/', '', $address);
        return strlen($normalizedAddress) >= 6 &&
            strlen($normalizedAddress) <= 12 &&
            preg_match('/^[0-9A-Fa-f]+$/', $normalizedAddress) === 1;
    }

    /*
     * 获取MAC查询服务器列表
     * 
     * @return array
     */
    private function getMacServers()
    {
        return [
            'api.maclookup.app/v2/macs',
            'api.macvendors.com',
        ];
    }

    /*
     * 获取MAC服务器列表
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function servers()
    {
        return response()->json([
            'servers' => $this->getMacServers()
        ]);
    }

    /**
     * 从指定服务器查询MAC信息
     *
     * @param string $query
     * @param string $server
     * @return array|null
     */
    private function queryMacServer($query, $server)
    {
        try {
            $url = 'https://' . $server . '/' . $query;
            $response = Http::get($url);
            $data = $response->json();

            if (!isset($data['success']) || $data['success'] !== true) {
                return null;
            }

            return $this->modifyData($data);
        } catch (\Exception $e) {
            Log::error("MAC lookup failed for server {$server}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 修改API返回的数据格式
     *
     * @param array $data
     * @return array
     */
    private function modifyData($data)
    {
        // 检查单播/多播以及本地/全球地址
        $firstByte = hexdec(substr($data['macPrefix'], 0, 2));
        $isMulticast = ($firstByte & 0x01) === 0x01;
        $isLocal = ($firstByte & 0x02) === 0x02;

        $data['isMulticast'] = $isMulticast;
        $data['isLocal'] = $isLocal;
        $data['isGlobal'] = !$isLocal;
        $data['isUnicast'] = !$isMulticast;

        // 格式化MAC地址和其他字段
        $data['macPrefix'] = isset($data['macPrefix']) ? implode(':', str_split($data['macPrefix'], 2)) : 'N/A';
        $data['company'] = $data['company'] ?? 'N/A';
        $data['country'] = $data['country'] ?? 'N/A';
        $data['address'] = $data['address'] ?? 'N/A';
        $data['updated'] = $data['updated'] ?? 'N/A';
        $data['blockStart'] = isset($data['blockStart']) ? implode(':', str_split($data['blockStart'], 2)) : 'N/A';
        $data['blockEnd'] = isset($data['blockEnd']) ? implode(':', str_split($data['blockEnd'], 2)) : 'N/A';
        $data['blockSize'] = $data['blockSize'] ?? 'N/A';
        $data['blockType'] = $data['blockType'] ?? 'N/A';

        return $data;
    }

    /**
     * 处理MAC地址查询请求
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function query(Request $request)
    {
        // 获取查询参数
        $query = $request->query('q');
        if (!$query) {
            return response()->json(['error' => 'No MAC address provided'], 400);
        }

        // 标准化MAC地址
        $query = preg_replace('/[:-]/', '', $query);

        // 获取选择的服务器
        $selectedServers = $request->query('servers');
        $servers = $selectedServers ? explode(',', $selectedServers) : ['api.maclookup.app/v2/macs'];

        // 验证MAC地址是否合法
        if (!$this->isValidMAC($query)) {
            Log::debug('Invalid MAC address: ' . $query);
            return response()->json(['error' => 'Invalid MAC address'], 400);
        }

        // 生成缓存键
        $cacheKey = 'mac_lookup_' . md5($query . '_' . implode(',', $servers));

        // 从缓存获取或发起API请求
        $data = Cache::remember($cacheKey, 60 * 60 * 24, function () use ($query, $servers) {
            try {
                // 从多个服务器获取信息
                $results = [];
                foreach ($servers as $server) {
                    $info = $this->queryMacServer($query, $server);
                    if ($info) {
                        $results[$server] = $info;
                    }
                }

                if (empty($results)) {
                    return ['__error' => 'No data found for this MAC address', '__code' => 404];
                }

                return $results;
            } catch (\Exception $e) {
                Log::error('MAC lookup failed: ' . $e->getMessage());
                return ['__error' => $e->getMessage(), '__code' => 500];
            }
        });

        // 检查返回的数据是否包含错误
        if (isset($data['__error'])) {
            $errorMessage = $data['__error'];
            $errorCode = $data['__code'] ?? 500;
            return response()->json(['error' => $errorMessage], $errorCode);
        }

        // 返回成功结果，并添加缓存控制头
        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Expires', gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    }
}
