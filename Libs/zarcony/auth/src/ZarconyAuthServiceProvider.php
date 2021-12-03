<?php

namespace Zarcony\Auth;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ZarconyAuthServiceProvider extends ServiceProvider
{
    public function boot() {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/Config/Config.php', 'ZarconyAuth');
    }

    protected function registerRoutes () {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/./Routes/Routes.php');
        });
    }

    protected function routeConfiguration() {
        return [
            'prefix'        => config('ZarconyAuth.prefix'),
            'middleware'    => config('ZarconyAuth.generalMiddleware')
        ];
    }
}
