<?php

namespace VatsimDatafeed;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use VatsimDatafeed\StatusClasses\RootObject;

class Statusfile
{

    private static function do_curl(): string|bool
    {
        $url = Config::get('vatsimdatafeed.status_url');

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
        $cache_key = Config::get('vatsimdatafeed.cache_key');
        return Cache::remember($cache_key.'status.get',  60 * 60, function () {
            $data = self::do_curl();
            if(!$data)
                return null;
            else
                return RootObject::fromJson(json_decode($data));
        });
    }
}
