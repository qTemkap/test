<?php


Route::prefix('settings')->name('settings.')->group(function(){

    Route::prefix('access')->name('access.')->group(function (){

        Route::get('/', 'SettingsController@index')->name('index');

        Route::get('/users','SettingsController@users')->name('users');

        Route::get('/employee','SettingsController@employee')->name('employee');

        Route::post('/employee/add','SettingsController@addEmployee')->name('employee.add');

        Route::post('/employee_list','SettingsController@employee_list')->name('employee_list');

        Route::get('/employee_card/{id}','SettingsController@employee_card')->name('employee_card');

        Route::post('/employee/save','SettingsController@saveUser')->name('employee.save');

        Route::get('/rules','SettingsController@rules')->name('rules');

        Route::get('/rules/order','SettingsController@rulesOrder')->name('rules.order');

        Route::get('/rules/document','SettingsController@rulesDocument')->name('rules.document');

        Route::get('/rules/house-catalog','SettingsController@rulesHouseCatalog')->name('rules.house-catalog');

        Route::post('/save-rules','SettingsController@saveRules')->name('saveRules');


        Route::prefix('groups')->name('groups.')->group(function() {

            Route::get('/','GroupsController@index')->name('index');

            Route::post('{id}/update','GroupsController@update')->name('update');

        });


        Route::prefix('subgroups')->name('subgroups.')->group(function() {

            Route::get('/', 'GroupsController@getSubgroupsList')->name('get');

            Route::get('/search', 'GroupsController@searchSubgroups')->name('search');

            Route::post('/', 'GroupsController@addSubgroup')->name('add');

            Route::post('{id}/delete', 'GroupsController@deleteSubgroup')->name('delete');

            Route::post('{id}/update', 'GroupsController@updateSubgroup')->name('update');

            Route::get('{id}/settings', 'GroupsController@subgroupSettings')->name('settings');

            Route::post('{id}/settings/update', 'GroupsController@subgroupSettingsUpdate')->name('settings.update');

        });

        Route::prefix('departments')->name('departments.')->group(function () {

            Route::get('/','SettingsController@departments')->name('get');

            Route::post('add','DepartmentController@add')->name('add');

            Route::post('{id}/update','DepartmentController@update')->name('update');

            Route::post('{id}/employees/update','DepartmentController@updateEmployees')->name('employees.update');

            Route::post('{id}/employees/add','DepartmentController@addEmployees')->name('employees.add');

        });

        Route::post('/update-departments','DepartmentController@get')->name('updateDepartments');

    });


    Route::prefix('options')->name('options.')->group(function(){

        Route::get('/','SettingsController@options')->name('index');

        Route::get('/pdf','SettingsController@optionsPdf')->name('pdf');

        Route::get('/mask','SettingsController@mask')->name('mask');

        Route::get('/double_object','SettingsController@double_object')->name('double_object');

        Route::post('/save_double_object','SettingsController@save_double_object')->name('save_double_object');

        Route::get('/logo','SettingsController@optionsLogo')->name('logo');

        Route::post('uploadLogo','SettingsController@uploadLogo')->name('uploadLogo');

        Route::post('deleteLogo','SettingsController@deleteLogo')->name('deleteLogo');

        Route::get('object-card','SettingsController@optionsObjectCard')->name('objectCard');

        Route::get('document','SettingsController@documents')->name('document');

        Route::get('mandatory','SettingsController@mandatory')->name('mandatory');

        Route::post('mandatory/save','SettingsController@save_mandatory')->name('mandatory.save');

        Route::get('analogs','SettingsController@analogs')->name('analogs');

        Route::post('save_analogs','SettingsController@save_analogs')->name('save_analogs');

        Route::post('save_mask','SettingsController@save_mask')->name('save_mask');

        Route::post('document_render','SettingsController@documentsRender')->name('document_render');

        Route::get('lang','SettingsController@lang')->name('lang');

        Route::post('update-lang','LocaleController@update')->name('lang.update');

        Route::get('lang/{locale}/lang_inner','SettingsController@lang_inner')->name('lang.lang_inner');

        Route::post('lang/translation/update','LocaleController@updateTranslation')->name('lang.translation.update');

        Route::post('filters/save','SettingsController@saveDefaultFilters')->name('filters.save');
        Route::get('filters/{model?}','SettingsController@defaultFilters')->defaults('model', 'App\\Building')->name('filters');

    });

    Route::prefix('crm')->name('crm.')->group(function (){

        Route::get('/','SettingsController@crm')->name('index');

        Route::get('/notifications','SettingsController@crmNotification')->name('notification');

        Route::get('/leads','SettingsController@crmLeads')->name('leads');

        Route::get('/deals','SettingsController@crmDeals')->name('deals');

        Route::post('/setOptionLeads','SettingsController@setOptionLeads')->name('setOptionLeads');

        Route::post('/setOptionDeals','SettingsController@setOptionDeals')->name('setOptionDeals');

        Route::get('/notifications_order','SettingsController@crmNotificationOrder')->name('notification_order');

        Route::post('/save_notifications_status','SettingsController@saveNotificationStatus')->name('save_notifications_status');

        Route::get('/global_auth','SettingsController@global_auth')->name('global_auth');

        Route::get('/global_auth_order','SettingsController@global_auth_order')->name('global_auth_order');

        Route::post('/save_global_auth','SettingsController@save_global_auth')->name('save_global_auth');

    });

    Route::prefix('dictionary')->name('dictionary.')->group(function (){

        Route::get('/','SettingsController@dictionary')->name('index');

        Route::get('/dictionary_spr','SettingsController@dictionary_spr')->name('dictionary_spr');

        Route::get('/dictionarys','SettingsController@dictionarys')->name('dictionarys');

        Route::post('/save_spr','SettingsController@save_spr')->name('save_spr');

        Route::post('/add_spr_row','SettingsController@add_spr_row')->name('add_spr_row');

        Route::post('/delete_spr_row','SettingsController@delete_spr_row')->name('delete_spr_row');

        Route::get('check','DictionaryController@check');

    });

    Route::prefix('address')->name('address.')->group(function (){

        Route::get('/','SettingsController@address')->name('index');

        Route::post('/','SettingsController@createAddress')->name('create');

        Route::get('/default','SettingsController@addressDefault')->name('default');

        Route::post('/regions','SettingsController@addressGetRegions')->name('regions');

        Route::post('/areas','SettingsController@addressGetAreas')->name('areas');

        Route::post('/cities','SettingsController@addressGetCities')->name('cities');

        Route::post('/set-default-address','SettingsController@setDefaultAddress')->name('setDefault');

        Route::post('/get-streets','AddressController@getStreets')->name('getStreets');

        Route::post('/add-streets-from','AddressController@addStreetForm')->name('addStreetForm');

        //catalog of buildings

        Route::get('/catalog','AddressController@catalog')->name('catalog');

        Route::post('/catalog_list','AddressController@getCatalogList')->name('catalog_list');

        Route::get('/catalog/{id}/delete','AddressController@catalogDelete')->name('catalog.delete');

    });

    Route::prefix('import')->name('import.')->group(function (){

        Route::get('/','SettingsController@import')->name('index');

        Route::get('/base','SettingsController@base')->name('base');
    });

    Route::prefix('export')->name('export.')->group(function (){

        Route::get('/','SettingsController@export')->name('index');

        Route::get('/sites','SettingsController@exportSites')->name('sites');

        Route::post('/saveSite','SettingsController@saveSite')->name('saveSite');

        Route::post('/deleteSite','SettingsController@deleteSite')->name('deleteSite');

        Route::post('/apiToken','SettingsController@apiToken')->name('apiToken');

        Route::get('/olx/authorize','SettingsController@getOlxAccessToken')->name('getOlxAccessToken');

    });

    Route::get('/setting_by_object',function (){
        return view('setting.by_object');
    });

    Route::get('/setting_by_object2',function (){
        return view('setting.by_object2');
    });


});
