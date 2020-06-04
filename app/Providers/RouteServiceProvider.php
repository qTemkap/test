<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';
    protected $ObjectApiNamespace = 'App\Http\Controllers\Api';
    protected $SearchNamespace = 'App\Http\Controllers\Search';
    protected $AdminNamespace = 'App\Http\Controllers\Admin';
    protected $BitrixNamespace = 'App\Http\Controllers\Bitrix';
    protected $ExportApiNamespace = 'App\Http\Controllers\Api\Export';
    protected $MicroApiNamespace = 'App\Http\Controllers\Api\MicroApi';
    protected $auth;

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->auth = 'bitrix:init';
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        //

        $this->mapObjectApiRoutes();

        $this->mapSearchRoutes();

        $this->mapAdminRoutes();

        $this->mapBitrixRoutes();

        $this->mapExportApiRoutes();

    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware(['web','settings'])
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }

    protected function mapObjectApiRoutes()
    {
        Route::prefix('object-api')
            ->middleware(['web', $this->auth, 'settings'])
            ->namespace($this->ObjectApiNamespace)
            ->group(base_path('routes/ObjectApi/api.php'));
    }

    protected function mapExportApiRoutes()
    {
        Route::prefix('export-api')
            ->middleware(['api','export'])
            ->namespace($this->ExportApiNamespace)
            ->group(base_path('routes/Export/api.php'));
    }

    protected function mapSearchRoutes()
    {
        Route::prefix('search')
            ->middleware(['web', $this->auth, 'settings'])
            ->name('search.')
            ->namespace($this->SearchNamespace)
            ->group(base_path('routes/Search/search.php'));
    }

    protected function mapAdminRoutes()
    {
        Route::prefix('administrator')
            ->middleware(['web', $this->auth, 'administrator', 'settings'])
            ->name('administrator.')
            ->namespace($this->AdminNamespace)
            ->group(base_path('routes/Admin/admin.php'));
    }

    protected function mapBitrixRoutes()
    {
        Route::prefix('bitrix-v1')
            ->middleware(['web', $this->auth, 'role:administrator','settings'])
            ->name('bitrix.')
            ->namespace($this->BitrixNamespace)
            ->group(base_path('routes/Bitrix/bitrix.php'));
    }
}
