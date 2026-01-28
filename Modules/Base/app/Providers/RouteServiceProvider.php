<?php

namespace Modules\Base\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Base';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        $apiRoutesPath = module_path($this->name, '/routes/api.php');
        if (file_exists($apiRoutesPath)) {
            Route::middleware('api')->prefix('api')->name('api.')->group($apiRoutesPath);
        }
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        $webRoutesPath = module_path($this->name, '/routes/web.php');
        if (file_exists($webRoutesPath)) {
            Route::middleware('web')->group($webRoutesPath);
        }
    }
}
