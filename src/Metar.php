<?php

namespace VatsimData;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use VatsimData\Helpers\CacheFreshness;

class Metar
{
    private static function do_curl(string $icao): string|bool
    {
        $metar_urls = Statusfile::get()->metar;
        $url = $metar_urls[array_rand($metar_urls)].'?id='.$icao;

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

    public static function get(string $icao): ?string
    {
        $cache_key = Config::get('vatsimdata.cache_key');
        $ttl = Config::get('vatsimdata.metar_cache_ttl', 300);
        $icao = strtoupper(trim($icao));

        $cacheKey = $cache_key."metar.get.$icao";

        return Cache::remember($cacheKey, $ttl, function () use ($icao, $cacheKey, $ttl) {
            $data = self::do_curl($icao);
            if (! $data) {
                return null;
            }

            CacheFreshness::record($cacheKey, (int) $ttl);

            return $data;
        });
    }

    /** Return when this package last fetched the METAR for an ICAO code successfully. */
    public static function FetchedAt(string $icao): ?DateTimeImmutable
    {
        $icao = strtoupper(trim($icao));

        return CacheFreshness::get(Config::get('vatsimdata.cache_key')."metar.get.$icao");
    }
}
