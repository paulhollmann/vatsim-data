<?php
namespace VatsimDatafeed;
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/courier.php' => config_path('vatsimdatafeed.php'),
        ]);
    }

    public function register(): void{
        $this->mergeConfigFrom(
            __DIR__.'/../config/courier.php', 'courier'
        );
    }
}
