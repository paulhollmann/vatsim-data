<?php

namespace VatsimDatafeed\VatsimDatafeed;

use Illuminate\Support\Facades\Cache;

class Datafeed
{
    protected static string $_baseStatusUrl = 'https://status.vatsim.net/status.json';

    public static function get()
    {
        return Cache::remember('net.vatsim.datafeed', 59, function () {
            $status = json_decode(self::_downloadStatusFile());
            if ($status == null || $status == false) {
                Cache::forget('net.vatsim.status');
                return false;
            }
            $availableUrls = $status->data->v3;
            $url = $availableUrls[rand(0, sizeof($availableUrls) - 1)];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;
        });
    }

    public static function pilots()
    {
        $df = self::get();
        if ($df !== false) {
            return json_decode($df)->pilots;
        } else {
            return [];
        }
    }


    private static function _downloadStatusFile():string
    {
        return Cache::remember('net.vatsim.status', 24 * 60 * 60, function () {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::$_baseStatusUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;
        });
    }
}
