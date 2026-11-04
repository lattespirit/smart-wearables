<?php

namespace App\Modules\Ota\Providers;

use Illuminate\Support\ServiceProvider;

class OtaServiceProvider extends ServiceProvider
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
