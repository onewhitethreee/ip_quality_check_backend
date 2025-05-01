<?php

namespace App\Http\Controllers;
use MaxMind\Db\Reader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeoIpController extends Controller
{
    private static $cityLookup = null;
    private static $asnLookup = null;
    private const CACHE_TTL = 3600; // 1 hour cache

    public function __construct()
    {
        $this->initDatabase();
    }

    private function initDatabase()
    {
        try {
            $cityPath = base_path("storage/app/GeoLite2-City.mmdb");
            $asnPath = base_path('storage/app/GeoLite2-ASN.mmdb');

            if (!file_exists($cityPath) || !file_exists($asnPath)) {
                throw new \Exception("Required MaxMind database files are missing");
            }

            if (self::$cityLookup === null) {
                self::$cityLookup = new Reader($cityPath);
            }

            if (self::$asnLookup === null) {
                self::$asnLookup = new Reader($asnPath);
            }
        } catch (\Exception $e) {
            Log::error("Failed to initialize MaxMind databases: " . $e->getMessage());
            throw $e;
        }
    }

    public function lookup(Request $request)
    {
        $ip = $request->query('ip', $request->ip());
        $lang = in_array($request->query('lang'), ['zh-CN', 'en', 'fr']) ? $request->query('lang') : 'en';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json(['error' => 'Invalid IP address'], 400);
        }

        // Generate cache key
        $cacheKey = "geoip:{$ip}:{$lang}";

        // Try to get from cache first
        if ($cachedResult = Cache::get($cacheKey)) {
            return response()->json($cachedResult);
        }

        try {
            if (self::$cityLookup === null || self::$asnLookup === null) {
                throw new \Exception("MaxMind databases are not loaded properly");
            }

            $city = self::$cityLookup->get($ip);
            $asn = self::$asnLookup->get($ip);

            $result = $this->modifyJson($ip, $lang, $city, $asn);
            
            // Cache the result
            Cache::put($cacheKey, $result, self::CACHE_TTL);

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