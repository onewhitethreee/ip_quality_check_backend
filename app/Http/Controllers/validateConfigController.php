<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class validateConfigController extends Controller
{
    public function validateConfig(Request $request)
    {
        if($request->method() !== 'GET') {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        # 从env文件中获取配置
        $configs = [
            'map' => config('services.google_map_api_key'),
            'ipInfo' => config('services.ipinfo_api_token'),
            'ipChecking' => config('services.ipchecking_api_key'),
            'ip2location' => config('services.ip2location_api_key'),
            'cloudflare' => config('services.cloudflare_api'),
            'ipapiis' => config('services.ipapiis_api_key'),
        ];

        $result = [];
        # 检查配置是否为空
        foreach($configs as $key => $value) {
            // Log::info($key . ' => ' . $value);
            $result[$key] = !empty($value);
            // Log::info($key . ' => ' . $result[$key]);
        }
        return response()->json($result);
    }
}