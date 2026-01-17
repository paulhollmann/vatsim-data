<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

// -------------------------
// Minimal Laravel-like environment
// -------------------------
$container = new Container;
Facade::setFacadeApplication($container);

// Config binding
$container->singleton('config', function () {
    return new ConfigRepository([
        'vatsimdata' => [
            'use_datafeed_cache' => false,
            'datafeed_cached_url' => 'http://docker.vatsim-germany.org:8007/datafeed',
            'datafeed_uncached_url' => 'https://data.vatsim.net/v3/vatsim-data.json',
            'status_url' => 'https://status.vatsim.net/status.json',
            'cache_key' => 'vatsimdatafeed.',
            'local_atc_pattern' => '/(ED[A-Z]{2}|ET[AHIMNS]{1}[A-Z]{1})/A',
            'local_airspace_polygon' => 'POLYGON(( 5.8663 47.2701, 6.0431 47.5938, 6.1566 48.0468, 6.6582 49.2019, 7.1004 49.4172, 7.5936 49.4444, 8.0993 49.0178, 8.6512 49.0206, 9.9219 49.3916, 10.5666 49.2163, 11.0274 49.4172, 11.6349 50.1188, 12.5184 50.0700, 13.0759 50.3766, 13.5950 51.1089, 14.0249 51.1141, 14.3533 51.0023, 14.7749 51.0023, 15.0169 51.1067, 15.0419 55.0583, 14.7405 54.9120, 14.5000 54.7000, 13.9600 54.5000, 13.0759 54.7000, 12.0899 54.7000, 11.0450 54.5000, 10.2100 54.5000, 9.9219 54.3000, 9.5600 54.2000, 8.5260 53.9000, 7.1004 53.5000, 6.0431 53.2000, 5.8663 52.8000, 5.8663 47.2701 ))',
        ],
    ]);
});

// Cache binding
$container->singleton('cache', function () {
    return new CacheRepository(new ArrayStore);
});

// -------------------------
// Interactive PsySH shell
// -------------------------
echo "✅ VatsimData interactive shell\n";
echo "Type 'exit' or Ctrl+C to quit.\n\n";

$shell = new \Psy\Shell;
$shell->run();
