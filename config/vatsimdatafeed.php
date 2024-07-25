<?php

return [
    'use_datafeed_cache' => env('VATSIM_DATAFEED_USE_CACHE', false),
    'datafeed_cached_url' => env('VATSIM_DATAFEED_CACHED_URL', 'http://docker.vatsim-germany.org:8007/datafeed'),
    'datafeed_uncached_url'=> env('VATSIM_DATAFEED_UNCACHED_URL', 'https://data.vatsim.net/v3/vatsim-data.json'),
    'status_url' => env('VATSIM_DATAFEED_UNCACHED_URL', 'https://status.vatsim.net/status.json'),
    'cache_key' => env('VATSIM_DATAFEED_CACHE_KEY_PREFIX', 'vatsimdatafeed.'),
    'local_atc_pattern' => env('VATSIM_DATAFEED_LOCAL_ATC_PATTERN', '/(ED[A-Z]{2}|ET[AHIMNS]{1}[A-Z]{1})/A'),
];
