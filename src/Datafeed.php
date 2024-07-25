<?php

namespace VatsimDatafeed;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use VatsimDatafeed\DatafeedClasses\Controllers;
use VatsimDatafeed\DatafeedClasses\Pilots;
use VatsimDatafeed\DatafeedClasses\RootObject;

class Datafeed
{
    private static function do_curl(string $url): string|bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public static function get(): ?RootObject
    {
        $use_df_cache = Config::get('vatsimdatafeed.use_datafeed_cache');
        $url_cached = Config::get('vatsimdatafeed.datafeed_cached_url');
        $url_uncached = Config::get('vatsimdatafeed.datafeed_uncached_url');
        $url = $use_df_cache ? $url_cached : $url_uncached;

        $cache_key = Config::get('vatsimdatafeed.cache_key');

        return Cache::remember($cache_key.'datafeed.get', 20, function () use ($use_df_cache, $url) {
                $data = self::do_curl($url);
                if(!$data)
                    return null;
                if ($use_df_cache)
                    return RootObject::fromJson(json_decode($data)?->data);
                else
                    return RootObject::fromJson(json_decode($data));
        });
    }

    /**
     * @return Pilots[]
     */
    public static function Pilots(): array
    {
        $df = self::get();
        return $df ? $df->pilots : [];
    }

    /**
     * @return Controllers[]
     */
    public static function Controllers(): array
    {
        $df = self::get();
        return $df ? $df->controllers : [];
    }
    /**
     * @return Controllers[]
     */
    public static function ControllersLocal(): array
    {
        $resultList = [];
        $atcs = self::Controllers();
        $local_atc_pattern = Config::get('vatsimdatafeed.local_atc_pattern');
        foreach ($atcs as $a) {
            if (Str::contains($a->callsign, 'OBS')) {
                continue;
            }
            if (preg_match($local_atc_pattern, $a->callsign) == 1) {
                $resultList[] = $a;
            }
        }
        return $resultList;
    }
}
