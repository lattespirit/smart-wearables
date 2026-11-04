<?php

namespace App\Modules\Firmware\Providers;

use Illuminate\Support\ServiceProvider;

class FirmwareServiceProvider extends ServiceProvider
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
