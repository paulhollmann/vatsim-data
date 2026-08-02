<?php

namespace VatsimData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use VatsimData\TransceiverClasses\RootObject;
use VatsimData\TransceiverClasses\TransceiverOwner;

class TransceiverData
{
    private static function do_curl(): string|bool
    {
        $urls = Statusfile::get()->data->transceivers;
        $url = $urls[array_rand($urls)];

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

    /**
     * @return TransceiverOwner[]
     */
    public static function get(): array
    {
        $cache_key = Config::get('vatsimdata.cache_key');
        $ttl = Config::get('vatsimdata.transceiver_cache_ttl', 120);

        $cacheKey = $cache_key.'transceiver.get';
        $payload = Cache::get($cacheKey);

        if (! is_string($payload)) {
            if ($payload !== null) {
                Cache::forget($cacheKey);
            }

            $payload = self::do_curl();

            if (! is_string($payload) || $payload === '') {
                return [];
            }

            if (! is_array(json_decode($payload))) {
                return [];
            }

            Cache::put($cacheKey, $payload, $ttl);
        }

        $decoded = json_decode($payload);

        try {
            return is_array($decoded) ? RootObject::fromJson($decoded) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function Owner(string $id): ?TransceiverOwner
    {
        $id = strtoupper($id);
        $id = preg_replace('/[^A-Z0-9_]/', '', $id);

        $owners = self::get();
        foreach ($owners as $owner) {
            if ($owner->callsign == $id) {
                return $owner;
            }
        }

        return null;
    }
}
