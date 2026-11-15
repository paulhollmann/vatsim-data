<?php

namespace VatsimData;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use VatsimData\Helpers\CacheFreshness;

class Metar
{
    private static function do_curl(string $prefix): string|false
    {
        $url = rtrim(Config::get('vatsimdata.metar_url', 'https://metar.vatsim.net'), '/').'/'.$prefix.'?format=json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'vatsim-data (https://github.com/paulhollmann/vatsim-data)');
        $data = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return $status === 200 && is_string($data) ? $data : false;
    }

    public static function get(string $icao): ?string
    {
        $icao = strtoupper(trim($icao));
        if (strlen($icao) < 2) {
            return null;
        }

        $prefix = substr($icao, 0, 2);
        $cacheKey = Config::get('vatsimdata.cache_key')."metar.prefix.$prefix";
        $ttl = Config::get('vatsimdata.metar_cache_ttl', 300);

        $metars = Cache::remember($cacheKey, $ttl, function () use ($prefix, $cacheKey, $ttl) {
            $data = self::do_curl($prefix);
            if ($data === false) {
                return null;
            }

            $decoded = json_decode($data, true);
            if (! is_array($decoded) || ! array_is_list($decoded)) {
                return null;
            }

            $metars = [];
            foreach ($decoded as $entry) {
                if (! is_array($entry) || ! isset($entry['id'], $entry['metar'])
                    || ! is_string($entry['id']) || ! is_string($entry['metar'])
                    || ! preg_match('/^[A-Z0-9]{4}$/', $entry['id']) || $entry['metar'] === '') {
                    return null;
                }

                $metars[$entry['id']] = $entry['id'].' '.$entry['metar'];
            }

            CacheFreshness::record($cacheKey, (int) $ttl);

            return $metars;
        });

        return is_array($metars) ? ($metars[$icao] ?? null) : null;
    }

    /** Return when this package last fetched the METAR for an ICAO code successfully. */
    public static function FetchedAt(string $icao): ?DateTimeImmutable
    {
        $icao = strtoupper(trim($icao));
        if (strlen($icao) < 2) {
            return null;
        }

        $cacheKey = Config::get('vatsimdata.cache_key').'metar.prefix.'.substr($icao, 0, 2);
        $metars = Cache::get($cacheKey);

        return is_array($metars) && isset($metars[$icao]) ? CacheFreshness::get($cacheKey) : null;
    }
}
