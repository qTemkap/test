<?php

Route::prefix('department')->name('department.')->group(function(){
    Route::get('/get','DepartmentController@get')->name('get');
});
