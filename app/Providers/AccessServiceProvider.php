<?php

namespace App\Providers;

use App\Helpers\Access;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('access',function(){

            return new Access();

        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
