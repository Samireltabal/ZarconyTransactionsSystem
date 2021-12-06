<?php

namespace Zarcony\Auth;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Zarcony\Auth\Commands\ZarconyInitCommand;


class ZarconyAuthServiceProvider extends ServiceProvider
{
    public function boot() {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();
        $this->registerAdminRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ZarconyInitCommand::class,
            ]);
        }
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

    protected function registerAdminRoutes () {
        Route::group($this->adminRouteConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/./Routes/AdminRoutes.php');
        });
    }

    protected function routeConfiguration() {
        return [
            'prefix'        => config('ZarconyAuth.prefix'),
            'middleware'    => config('ZarconyAuth.generalMiddleware')
        ];
    }
    protected function adminRouteConfiguration() {
        return [
            'prefix'        => config('ZarconyAuth.admin_prefix'),
            'middleware'    => config('ZarconyAuth.admin_middleware')
        ];
    }
}
