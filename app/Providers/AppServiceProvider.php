<?php

namespace App\Providers;

use App\Services\ApiOLXService;
use App\Helpers\Microsite;
use App\Services\DuplicatePermissionCheckService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\SourceEvents;
use App\SprTypeForEvent;
use App\SprListTypeForEvent;
use App\SprStatusForEvent;
use App\SprResultForEvent;
use App\Models\Settings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['request']->server->set('HTTPS','on');

        Blade::if('bitrix', function () {
            return auth()->user()->hasAccessToBitrix();
        });

        Blade::directive('address', function ($expression) {

            $expression = collect(explode(',', $expression))->map(function ($item) {
                return trim($item);
            });
            return "<?php echo implode(', ',array_filter({$expression}));  ?>";
        });

        Blade::directive('hide_number', function ($expression) {

            return '<?php
                $count = 5;
                
                if(strpos($phone_num, "+") !== false){
                    ++$count;
                }

                if(strpos($phone_num, "(") !== false){
                    ++$count;
                }

                if(strpos(substr($phone_num, 0, 8), ")") !== false){
                    ++$count;
                }
                
                if(strpos(substr($phone_num, 0, 8), " ") !== false){
                    ++$count;
                }

                $length = strlen($phone_num);
                $last = $length-$count;
                $phone_num = substr($phone_num, 0, $count);

                if($last>=0) {
                } else { $last = 0; }
                unset($count);
                echo $phone_num.str_repeat("*", (int)$last);
                ?>';
        });

        Blade::directive('get_percent', function($expression) {
            $expression = collect(explode(',', $expression))->toArray();
            return "<?php echo $expression[0]>0?round($expression[1]*100/$expression[0]):0;  ?>";
        });

        Blade::directive('get_percent_price', function($expression) {
            $expression = collect(explode(',', $expression))->toArray();
            return "<?php echo ($expression[0]>0&&$expression[1]>0)?round(($expression[1]/$expression[0]-1)*100):0;  ?>";
        });

        if (Schema::hasTable('source_for_events'))
        {
            $source_events = SourceEvents::all();
            $typeForEvent = SprTypeForEvent::all();
            $statusForEvent = SprStatusForEvent::all();
            $resultForEvent = SprResultForEvent::all();
            $listTypeEvents = SprListTypeForEvent::getList();

            view()->share('source_events', $source_events);
            view()->share('typeForEvent', $typeForEvent);
            view()->share('listTypeEvents', $listTypeEvents);
            view()->share('statusForEvent', $statusForEvent);
            view()->share('resultForEvent', $resultForEvent);
        }

        if(Schema::hasTable('settings')) {
            $option = Settings::where('option', 'phone_mask')->first();

            if($option) {
                $phone_mask = collect(json_decode($option->value))->toArray();
                view()->share('phone_mask', $phone_mask);
            }

            $hookStatus_row = Settings::where('option', 'hookStatus')->first();

            $hookStatus = false;

            if(!is_null($hookStatus_row)) {
                if(!empty(json_decode($hookStatus_row->value))) {
                    $hookStatus = true;
                }
            }

            view()->share('hookStatus', $hookStatus);

            $double_object = Settings::where('option', 'double_object')->first();

            if($double_object) {
                $double_object = $double_object->value;
                if($double_object == true) {
                    view()->share('double_object', true);
                } else {
                    view()->share('double_object', false);
                }
            } else {
                view()->share('double_object', false);
            }
        }

        $this->app->singleton(ApiOLXService::class, function() {
            return new ApiOLXService(
                config('services.olx.client_id'),
                config('services.olx.client_secret')
            );
        });

        $this->app->bind('microsite',function(){
            return new Microsite();
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment() !== 'production') {
            $this->app->register(\Way\Generators\GeneratorsServiceProvider::class);
            $this->app->register(\Xethron\MigrationsGenerator\MigrationsGeneratorServiceProvider::class);
        }

        $this->app->bind('App\Services\GoogleSheetsService', function ($app) {
            return new \App\Services\GoogleSheetsService();
        });

        $this->app->bind('App\Services\MandatoryService', function ($app) {
            return new \App\Services\MandatoryService();
        });

        $this->app->bind(DuplicatePermissionCheckService::class, function() {
            return new DuplicatePermissionCheckService(auth()->user()->getDepartment());
        });
    }
}
