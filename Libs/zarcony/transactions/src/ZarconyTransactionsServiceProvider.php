<?php 
  namespace Zarcony\Transactions;

  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\ServiceProvider;

  class ZarconyTransactionsServiceProvider extends ServiceProvider {

    public function boot () {
      $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
      $this->registerRoutes();
      $this->registerAdminRoutes();
    }

    public function register () {
      $this->mergeConfigFrom(__DIR__.'/Config/Config.php', 'transactions');
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

    protected function adminRouteConfiguration() {
        return [
            'prefix'        => config('transactions.admin_prefix'),
            'middleware'    => config('transactions.admin_middleware')
        ];
    }

    protected function routeConfiguration () {
      return [
        'prefix'        => config('transactions.prefix'),
        'middleware'    => config('transactions.general_middleware')
      ];
    }

  }