<?php

namespace VatsimData;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use VatsimData\Helpers\CacheFreshness;
use VatsimData\StatusClasses\RootObject;

class Statusfile
{
    private static function do_curl(): string|bool
    {
        $url = Config::get('vatsimdata.status_url');

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
        $cache_key = Config::get('vatsimdata.cache_key');
        $cacheKey = $cache_key.'status.get';
        $payload = Cache::get($cacheKey);

        if (! is_string($payload)) {
            if ($payload !== null) {
                Cache::forget($cacheKey);
            }

            $payload = self::do_curl();

            if (! is_string($payload) || $payload === '') {
                return null;
            }

            if (! is_object(json_decode($payload))) {
                return null;
            }

            Cache::put($cacheKey, $payload, 60 * 60);
            CacheFreshness::record($cacheKey, 60 * 60);
        }

        $decoded = json_decode($payload);

        try {
            return is_object($decoded) ? RootObject::fromJson($decoded) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Return when this package last fetched the status file successfully. */
    public static function FetchedAt(): ?DateTimeImmutable
    {
        return CacheFreshness::get(Config::get('vatsimdata.cache_key').'status.get');
    }
}
