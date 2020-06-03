<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('export')->group(function() {

    Route::prefix('employees')->group(function() {

        Route::get('/', 'EmployeesController@index');

        Route::get('/{user}', 'EmployeesController@get');

    });

    Route::prefix('objects')->group(function (){

        Route::get('/{type?}','ObjectsController@index');

        Route::get('/{type}/{id}','ObjectsController@get');

    });

    Route::prefix('orders')->group(function (){

        Route::get('/{type?}','OrdersController@index');

    });
    Route::prefix('house-catalog')->group(function (){

        Route::get('/','HousesCatalogController@index');

    });

    Route::prefix('webhook')->group(function (){

        Route::post('/','WebhookController@register');

    });
});

Route::post('webhook/test-listener','WebhookController@testListener');