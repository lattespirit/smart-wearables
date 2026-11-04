<?php

namespace App\Modules\Device\Providers;

use Illuminate\Support\ServiceProvider;

class DeviceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    public function boot(): void
    {
        //
    }
}
