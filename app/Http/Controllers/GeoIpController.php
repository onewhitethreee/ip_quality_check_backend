<?php

namespace App\Http\Controllers;
use MaxMind\Db\Reader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GeoIpController extends Controller
{
    private static $cityLookup = null;
    private static $asnLookup = null; // 修正变量名

    public function __construct()
    {
        $this->initDatabase();
    }

    private function initDatabase()
    {
        try {
            $cityPath = base_path("storage/app/GeoLite2-City.mmdb");
            $asnPath = base_path('storage/app/GeoLite2-ASN.mmdb');

            // 检查文件是否存在
            if (!file_exists($cityPath)) {
                Log::error("City database file does not exist: " . $cityPath);
            }

            if (!file_exists($asnPath)) {
                Log::error("ASN database file does not exist: " . $asnPath);
            }

            if (self::$cityLookup == null) {
                self::$cityLookup = new Reader($cityPath);
            }

            if (self::$asnLookup == null) { // 修正变量名
                self::$asnLookup = new Reader($asnPath);
            }
        } catch (\Exception $e) {
            Log::error("Failed to initialize MaxMind databases: " . $e->getMessage());
        }
    }

    public function lookup(Request $request)
    {
        // 可选：允许从查询参数传入IP，适合测试
        $ip = $request->query('ip', $request->ip());

        Log::info("Looking up IP: " . $ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json([
                'error' => 'Invalid IP address'
            ], 400);
        }

        $lang = in_array($request->query('lang'), ['zh-CN', 'en', 'fr']) ? $request->query('lang') : 'en';

        try {
            // 验证数据库是否已成功加载
            if (self::$cityLookup === null || self::$asnLookup === null) {
                throw new \Exception("MaxMind databases are not loaded properly");
            }

            $city = self::$cityLookup->get($ip);
            $asn = self::$asnLookup->get($ip); // 修正变量名

            

            $result = $this->modifyJson($ip, $lang, $city, $asn);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Error in GeoIP lookup: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function modifyJson($ip, $lang, $city, $asn)
    {
        $city = $city ?: [];
        $asn = $asn ?: [];

        return [
            'ip' => $ip,
            'city' => isset($city['city']['names'][$lang]) ? $city['city']['names'][$lang] :
                (isset($city['city']['names']['en']) ? $city['city']['names']['en'] : 'N/A'),
            'region' => isset($city['subdivisions'][0]['names'][$lang]) ? $city['subdivisions'][0]['names'][$lang] :
                (isset($city['subdivisions'][0]['names']['en']) ? $city['subdivisions'][0]['names']['en'] : 'N/A'),
            'country' => isset($city['country']['iso_code']) ? $city['country']['iso_code'] : 'N/A',
            'country_name' => isset($city['country']['names'][$lang]) ? $city['country']['names'][$lang] : 'N/A',
            'country_code' => isset($city['country']['iso_code']) ? $city['country']['iso_code'] : 'N/A',
            'latitude' => isset($city['location']['latitude']) ? $city['location']['latitude'] : 'N/A',
            'longitude' => isset($city['location']['longitude']) ? $city['location']['longitude'] : 'N/A',
            'asn' => isset($asn['autonomous_system_number']) ? 'AS' . $asn['autonomous_system_number'] : 'N/A',
            'org' => isset($asn['autonomous_system_organization']) ? $asn['autonomous_system_organization'] : 'N/A'
        ];
    }
}