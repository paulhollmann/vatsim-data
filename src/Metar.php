<?php

namespace VatsimData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class Metar
{
    private static function do_curl(string $icao): string|bool
    {
        $metar_urls = Statusfile::get()->metar;
        $url = $metar_urls[array_rand($metar_urls)] . '?id='. $icao;

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

    public static function get(string $icao):?string
    {
        $cache_key = Config::get('VatsimData.cache_key');

        return Cache::remember($cache_key."metar.get.$icao",  2*60, function () use ($icao) {
            $data = self::do_curl($icao);
            if(!$data)
                return null;
            else
                return $data;
        });
    }

}
