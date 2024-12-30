<?php

namespace VatsimData;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/vatsimdata.php' => config_path('vatsimdata.php'),
        ]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/vatsimdata.php', 'vatsimdata'
        );
    }
}
