<?php

$auth = 'bitrix:init';

Route::middleware([$auth, 'settings'])->group(function (){

    Route::post('check-auth','AuthController@check')->name('auth.check');

    Route::get('import-obj','Module\\ImportModuleController@index');
    Route::post('import-flats','Module\\ImportModuleController@importFlats')->name('import.flats');
    Route::post('import-flats/cancel','Module\\ImportModuleController@cancelImport')->name('import.flats.cancel');

    Route::get('/setNewColumnContact','AdminController@setNewColumnContact')->name('setNewColumnContact');
    Route::get('/setNewColumnTypeContact','AdminController@setNewColumnTypeContact')->name('setNewColumnTypeContact');
    Route::get('/setNewColumnDeal','AdminController@setNewColumnDeal')->name('setNewColumnDeal');

    Route::post('api-address','Api\LandUSController@create')->name('api_address');
    Route::post('api-photo','Api\LandUSController@uploadFile')->name('api_photo');
    Route::post('api-file-delete','Api\LandUSController@deleteFile')->name('api_file_delete');
    Route::post('api-file-base','Api\LandUSController@createFile')->name('api_file_base');
    Route::post('api-file-update','Api\LandUSController@update')->name('api_file_update');
    Route::post('api-land-update','Api\LandUSController@update')->name('api_land_update');
    Route::get('api-land-delete/{id}','Api\LandUSController@delete_obj')->name('objectApi.land.delete');
    Route::post('api-land-get_history_price','Api\LandUSController@get_history_price')->name('objectApi.land.history_price');
    Route::post('api-land-update_address','Api\LandUSController@update_address')->name('objectApi.land.update_address');
    Route::post('api-land-update_fast','Api\LandUSController@fast_update')->name('objectApi.land.fast_update');
    Route::post('api-land-zeroingCallEvent','Api\LandUSController@zeroingCallEvent')->name('objectApi.land.zeroingCallEvent');
    Route::post('api-file-plan','Api\LandUSController@uploadFilePlan')->name('objectApi.land.upload_photo_plan');
    Route::post('api-file-delete-plan','Api\LandUSController@deleteFilePlan')->name('objectApi.land.file_delete_plan');


    Route::get('bitrix-deal','UsDealController@bitrix');
    Route::get('bitrix-lead','LeadController@deal_option_on_crm');
    Route::get('setNewColumnLead','LeadController@setNewColumnLead');
    Route::get('setNewColumnLeadRe','LeadController@setNewColumnLeadRe');
    Route::get('setNewColumnLead3','LeadController@setNewColumnLead3');

    Route::prefix('deal')->name('deal.')->group(function(){
        Route::post('get-directions','UsDealController@getDirections')->name('get-directions');
    });

    Route::middleware(['role:administrator|director|office-manager|realtor'])->group(function (){


        Route::prefix('events')->name('events.')->group(function (){

            Route::get('/','EventController@index')->name('index');

        });

        Route::get('/setPriceForMeter','AdminController@setPriceForMeter')->name('setPriceForMeter');

        Route::prefix('export')->name('export.')->group(function (){
            Route::post('/export_check','ExportObjectController@export_check')->name('export_check');
            Route::post('/accept_export','ExportObjectController@accept_export')->name('accept_export');
        });

        Route::prefix('bitrix')->name('bitrix.')->group(function (){
            Route::any('client-objects/{id?}','UsContactsController@client_objects')->name('client-objects');
            Route::any('deal_object','UsDealController@deal_object')->name('deal-object');
            Route::any('lead_object','LeadController@lead_object')->name('lead-object');
            Route::get('update_lead_status','LeadController@bitrix');
            Route::get('update_deal_status','UsDealController@load_bitrix');
        });

        Route::post('createFiles','ExportController@createFiles')->name('createFiles');

        Route::any('/','FrontController@index')->name('index');
        Route::post('get-info-about-objects','FrontController@get_info')->name('front.get_info');

        Route::prefix('admin')->name('admin.')->middleware(['auth','role:administrator'])->group(function (){
            Route::get('/','AdminController@index')->name('index');
            Route::get('/users','AdminController@users')->name('users');
            Route::post('/bitrix_users','AdminController@get_bitrix_users')->name('bitrix_users');
            Route::post('/update_users','AdminController@update_users')->name('update_users');
            Route::post('/remove_users','AdminController@remove_users')->name('remove_users');
            //
            Route::get('/clients','AdminController@clients')->name('clients');
        });

        Route::prefix('commerce')->name('commerce.')->group(function(){
            Route::get('add','CommerceUSController@add')->name('add');
            Route::get('copy/{id}', 'CommerceUSController@copy')->name('copy');
            Route::post('create','CommerceUSController@create')->name('create');
            Route::get('edit/{id}','CommerceUSController@edit')->name('edit');
            Route::post('update','CommerceUSController@update')->name('update');
            Route::get('show/{id}','CommerceUSController@show')->name('show');
            Route::get('/','CommerceUSController@index')->name('index');
            Route::get('/restore/{id}', 'CommerceUSController@restore')->name('restore');

            Route::post('/check','CommerceUSController@check')->name('check');
            Route::post('upload-file','CommerceUSController@upload_file_ajax')->name('upload_file_ajax');
            Route::get('delete={id}','CommerceUSController@delete')->name('delete');
            Route::middleware('permission:change object status')->get('change-obj-status/{flat_id}/{obj_status_id}','CommerceUSController@change_obj_status')->name('change_obj_status');
            Route::middleware('permission:change object call status')->get('change-call-status/{flat_id}/{call_status_id}','CommerceUSController@change_call_status')->name('change_call_status');

        });

        Route::prefix('house_catalog')->name('house_catalog.')->group(function(){
            Route::get('/list','HouseCatalogController@index')->name('index');
            Route::get('/add_house','HouseCatalogController@add_house')->name('add_house');
            Route::get('setGroupForHouse','HouseCatalogController@setGroupForHouse')->name('setGroupForHouse');
            Route::get('/edit_house/{id}','HouseCatalogController@edit_house')->name('edit_house');
            Route::get('/edit_flat','HouseCatalogController@edit_flat')->name('edit_flat');
            Route::get('/import/check','HouseCatalogController@checkImportFinished')->name('import.check');
            Route::get('/import/all/{id?}','HouseCatalogController@import')->defaults('all', true)->name('import.all');
            Route::get('/import/{id?}','HouseCatalogController@import')->name('import');
            Route::get('/import/{id?}/template','HouseCatalogController@import')->defaults('template', true)->name('import.template');
            Route::get('/getFlatsList','HouseCatalogController@getFlatsList')->name('getFlatsList');
            Route::get('/check/finishEdit','HouseCatalogController@finishEdit')->name('check.finishedit');
        });

        Route::prefix('private-house')->name('private-house.')->group(function (){
            Route::get('add','HouseUSController@add')->name('add');
            Route::post('create','HouseUSController@create')->name('create');
            Route::get('copy/{id}', 'HouseUSController@copy')->name('copy');
            Route::get('edit/{id}','HouseUSController@edit')->name('edit');
            Route::post('update','HouseUSController@update')->name('update');
            Route::get('show/{id}','HouseUSController@show')->name('show');
            Route::get('/','HouseUSController@index')->name('index');
            Route::get('/restore/{id}', 'HouseUSController@restore')->name('restore');

            Route::get('edit_address/{id}','HouseUSController@edit_address')->name('edit_address');

            Route::post('/check','HouseUSController@check')->name('check');
            Route::post('upload-file','HouseUSController@upload_file_ajax')->name('upload_file_ajax');
            Route::get('delete={id}','HouseUSController@delete')->name('delete');
            Route::middleware('permission:change object status')->get('change-obj-status/{flat_id}/{obj_status_id}','HouseUSController@change_obj_status')->name('change_obj_status');
            Route::middleware('permission:change object call status')->get('change-call-status/{flat_id}/{call_status_id}','HouseUSController@change_call_status')->name('change_call_status');

        });

        Route::prefix('land')->name('land.')->group(function (){
            Route::get('add','LandUSController@add')->name('add');
            Route::post('create','LandUSController@create')->name('create');
            Route::get('copy/{id}', 'LandUSController@copy')->name('copy');
            Route::get('edit/{id}','LandUSController@edit')->name('edit');
            Route::post('update','LandUSController@update')->name('update');
            Route::get('show/{id}','LandUSController@show')->name('show');
            Route::get('/','LandUSController@index')->name('index');
            Route::get('/restore/{id}', 'LandUSController@restore')->name('restore');

            Route::get('edit_address/{id}','LandUSController@edit_address')->name('edit_address');

            Route::post('/check','LandUSController@check')->name('check');
            Route::post('upload-file','LandUSController@upload_file_ajax')->name('upload_file_ajax');
            Route::get('delete={id}','LandUSController@delete')->name('delete');
            Route::middleware('permission:change object status')->get('change-obj-status/{flat_id}/{obj_status_id}','LandUSController@change_obj_status')->name('change_obj_status');
            Route::middleware('permission:change object call status')->get('change-call-status/{flat_id}/{call_status_id}','LandUSController@change_call_status')->name('change_call_status');

        });

        Route::prefix('document')->name('document.')->group(function (){
            Route::get('/','DocumentUSController@index')->name('index');
            Route::get('/getFile','PDFController@getFile')->name('getFile');
            Route::post('/getFilePost','PDFController@getFilePost')->name('getFilePost');
            Route::post('/store','DocumentUSController@store')->name('store');
            Route::post('/getList','DocumentUSController@getList')->name('getList');
            Route::post('/destroy','DocumentUSController@destroy')->name('destroy');
            Route::post('/savePrint','DocumentUSController@goToPrint')->name('savePrint');
        });

        Route::prefix('pdf')->name('pdf.')->group(function (){
            Route::post('/list_list','PDFController@list_list')->name('list_list');
            Route::post('/list_table','PDFController@list_table')->name('list_table');
        });

        Route::prefix('affair')->name('affair.')->group(function() {
            Route::post('/create','AffairController@createAffair')->name('create');
            Route::post('/update','AffairController@update')->name('update');
            Route::post('/check_theme','AffairController@check_theme')->name('check_theme');
            Route::post('/get_addreass_object','AffairController@getAddressObject')->name('get_addreass_object');
            Route::post('/get_affair_by_id','AffairController@getById')->name('get_by_id');
        });

        Route::prefix('lead')->name('lead.')->group(function() {
            Route::post('/getCountObj','LeadController@getObjWithLead')->name('getCountObj');
            Route::post('/createLeads','LeadController@createLeads')->name('createLeads');
            Route::get('/bitrix','LeadController@bitrix')->name('bitrix');
        });

// Получение квартир определенного контакта.
        //Route::get('/contact/list', 'ContactController@getList');

// Получение аналогов объектов.
        //Route::get('/analogs/test', 'AnalogsController@test');

// Квартиры.

        Route::prefix('flat')->name('flat.')->group(function (){
            Route::get('add-us','FlatController@addUs');
            Route::get('list', 'FlatController@index')->name('index');
            Route::get('add', 'FlatController@add_get')->name('add');
            Route::get('copy/{id}', 'FlatController@copy')->name('copy');
            Route::post('add_request', 'FlatController@add')->name('add.request');
            Route::get('get={id}', 'FlatController@get')->name('show');
            Route::get('edit={id}', 'FlatController@edit_get')->name('edit');
            Route::post('edit_request', 'FlatController@change')->name('edit.request');

            Route::get('remove={id}', 'FlatController@remove')->name('delete');
            Route::get('restore={id}', 'FlatController@restore')->name('restore');
            Route::get('all/remove', 'FlatController@removeAll')->name('delete.all');
        });

        Route::post('/flat/add_request_ajax', 'FlatController@check')->name('check');
        Route::post('/flat/add_request_ajax_edit', 'FlatController@checkOnEdit')->name('checkOnEdit');
        Route::post('/flat/add_request_find_build', 'FlatController@find_build')->name('find.building');
        Route::post('/flat/add_request_region', 'FlatController@area')->name('region.check');
        Route::post('/flat/add_request_area', 'FlatController@city')->name('area.check');
        Route::post('/flat/add_request_street', 'FlatController@street')->name('street.check');
        Route::post('/flat/add_request_map', 'FlatController@map')->name('map.check');

        Route::post('/flat/get_street_name', 'FlatController@get_street_name')->name('street.name');

        Route::post('/flat/add_request_district', 'FlatController@district')->name('district.check');
        Route::post('/flat/add_request_microarea', 'FlatController@microarea')->name('microarea.check');

        Route::post('/flat/add_request_landmark', 'FlatController@landmark')->name('landmark');

        Route::post('/flat/get-microarea','FlatController@get_microarea')->name('get_microarea');
// Коммерческая недвижимость.
//        Route::get('/commerce/list', 'CommerceController@getList');
//        Route::get('/commerce/get={id}', 'CommerceController@get');
//        Route::get('/commerce/show', 'CommerceController@show');
//        Route::get('/commerce/remove={id}', 'CommerceController@remove');

// Дома/дачи.
        Route::get('/house/list', 'HouseController@getList');
        Route::get('/house/get={id}', 'HouseController@get');
        Route::get('/house/remove={id}', 'HouseController@remove');

// Участки.
        Route::get('/stead/list', 'SteadController@getList');
        Route::get('/stead/get={id}', 'SteadController@get');
        Route::get('/stead/remove={id}', 'SteadController@remove');

// Паркинг.
        Route::get('/parking/list', 'ParkingController@getList');
        Route::get('/parking/get={id}', 'ParkingController@get');
        Route::get('/parking/remove={id}', 'ParkingController@remove');

// Гараж.
        Route::get('/garage/list', 'GarageController@getList');
        Route::get('/garage/get={id}', 'GarageController@get');
        Route::get('/garage/remove={id}', 'GarageController@remove');






        Route::prefix('crm')->name('crm.')->group(function (){
            Route::get('client-handler','UsContactsController@client_on_crm_handler');
            Route::get('deal-handler','UsDealController@deal_on_crm_handler');
            Route::get('lead-handler','LeadController@lead_on_crm_handler');
            Route::get('affair-handler','AffairController@affair_on_crm_handler');
            Route::get('affair-task-handler','AffairController@affair_task_on_crm_handler');
            Route::get('lead-delete-handler','LeadController@delete_lead_on_crm_handler');
            Route::get('client-option-handler','UsContactsController@client_option_on_crm');
            Route::get('unbind-client-handler','UsContactsController@unbind');


            Route::get('order-option-handler','UsOrdersController@order_option_on_crm');
        });

        Route::prefix('window-crm')->name('window-crm.')->group(function (){
            Route::post('clients','UsContactsController@ajax_crm_window')->name('clients');
            Route::post('clients_multi','UsContactsController@ajax_crm_window_multi')->name('clients_multi');
            Route::post('new_clients_multi','UsContactsController@get_form_new_multi_client')->name('new_clients_multi');
            Route::post('clients-search_multi','UsContactsController@ajax_crm_windows_search_multi')->name('clients_search_multi');
            Route::post('clients-search','UsContactsController@ajax_crm_windows_search')->name('clients_search');
            Route::post('users','UsUserController@ajax_window_crm_users')->name('users');
            Route::post('users-search','UsUserController@ajax_window_crm_users_search')->name('users_search');
            Route::post('users-search_new','UsUserController@ajax_window_crm_users_search_new')->name('users_search_new');
            Route::post('clients-check','UsContactsController@ajax_crm_window_check')->name('clients_check');
            Route::post('clients-check-email','UsContactsController@ajax_crm_window_check_email')->name('clients_check_email');
            Route::post('clients-check_multi','UsContactsController@ajax_crm_window_check_multi')->name('clients_check_multi');
        });

        Route::prefix('contact')->name('contact.')->group(function (){
            Route::post('get_info_client','UsContactsController@getInfoContacts')->name('get_info_client');
            Route::get('get_info_client_json','UsContactsController@getInfoContactJson')->name('get_info_client.json');
        });

        Route::prefix('house')->name('house.')->group(function (){
            Route::get('show/{id}','UsBuildingController@show')->name('show');
            Route::get('edit/{id}','UsBuildingController@edit')->name('edit');
            Route::post('check','UsBuildingController@check')->name('check');
            Route::post('check_house','UsBuildingController@check_house')->name('check_house');
            Route::post('update','UsBuildingController@update')->name('update');
            Route::post('check_hc','UsBuildingController@check_hc')->name('check_hc');
            Route::post('show_chess','UsBuildingController@show_chess')->name('show_chess');
            Route::post('change_hc_name','UsBuildingController@changeName')->name('change_hc_name');

            Route::post('{building}/floor-plan', 'UsBuildingController@addFloorPlan')->name('floor-plan.add');
            Route::post('{building}/floor-plan/{floorPlan}/update', 'UsBuildingController@updateFloorPlan')->name('floor-plan.update');
            Route::post('{building}/floor-plan/{floorPlan}/delete', 'UsBuildingController@deleteFloorPlan')->name('floor-plan.delete');

            Route::post('{building}/flat-plan', 'UsBuildingController@addFlatPlan')->name('flat-plan.add');
            Route::post('{building}/flat-plan/{flatPlan}/update', 'UsBuildingController@updateFlatPlan')->name('flat-plan.update');
            Route::post('{building}/flat-plan/{flatPlan}/delete', 'UsBuildingController@deleteFlatPlan')->name('flat-plan.delete');

            Route::post('{building}/realtor-info/update', 'UsBuildingController@updateRealtorInfo')->name('realtor-info.update')->middleware('can:edit building realtor info');
            Route::post('{building}/sales-info/update', 'UsBuildingController@updateSalesInfo')->name('sales-info.update')->middleware('can:edit building sales');
        });

        Route::prefix('deal')->name('deal.')->group(function(){
            Route::post('add','UsDealController@add')->name('add');
        });

        Route::middleware('permission:change object status')->get('flat/change-obj-status/{flat_id}/{obj_status_id}','FlatController@change_obj_status')->name('flat.change_obj_status');
        Route::middleware('permission:change object call status')->get('flat/change-call-status/{flat_id}/{call_status_id}','FlatController@change_call_status')->name('flat.change_call_status');

        Route::post('flat/upload-file','FlatController@upload_file_ajax')->name('flat.upload_file_ajax');

        Route::prefix('history')->name('history.')->group(function() {
            Route::get('/','HistoryController@index')->name('index');
            Route::post('pdf_brok','HistoryController@set_pdf_brok_history')->name('pdf_brok');
            Route::post('pdf_client','HistoryController@set_pdf_client_history')->name('pdf_client');
            Route::post('get_history_obj','HistoryController@get_history_object')->name('get_history_obj');
            Route::post('get_history_all','HistoryController@get_all_history')->name('get_history_all');
            Route::post('check_user','HistoryController@check_user')->name('check_user');
        });


//Заявки
        Route::get('/orders', 'OrderController@list')->name('orders.list');
        Route::post('/orders/create', 'OrderController@create')->name('orders.create');
        Route::get('/orders/show/{id}', 'OrderController@show')->name('orders.show');
        Route::post('/orders/save/contact', 'OrderController@saveContact')->name('orders.save.contact');
        Route::post('/orders/update/{id}', 'OrderController@updateOrder')->name('orders.update');
        Route::get('/orders/edit/{id}', 'OrderController@editOrder')->name('orders.edit.order');
        Route::get('/orders/status/{id}/{status}', 'OrderController@status')->name('orders.status');
        Route::get('/orders/delete/{id}', 'OrderController@deleteOrder')->name('orders.delete');
        Route::get('/orders/add/to/work/{id}', 'OrderController@addToWork')->name('orders.add.to.work');
        Route::post('/orders/getsorted', 'OrderController@getSorted')->name('orders.getsorted');
        Route::post('/orders/getsortedfind', 'OrderController@getSortedFind')->name('orders.getsortedfind');
        Route::post('/orders/getEvetns', 'EventController@getForOrder')->name('orders.get_events');
        Route::get('/order/restore/{id}', 'OrderController@restore')->name('order.restore');

//Объекты заявок
        Route::get('/orders/toggle/status/obj/{id}/{status}', 'OrderObjController@toggleStatusObj')->name('orders.toggle.status.obj');
        Route::get('/orders/delete/obj/{id}', 'OrderObjController@deleteOrderObj')->name('orders.delete.order.obj');
        Route::get('/orders/add/obj/{orders_id}/{obj_id}', 'OrderObjController@addObject')->name('orders.add.obj');
        Route::post('/orders/update/obj/{obj_id}', 'OrderObjController@updateObject')->name('orders.update.obj');
        Route::get('/orders/update/obj/status/{obj_id}', 'OrderObjController@updateStatusObject')->name('orders.update.obj.status');
        Route::post('/orders/add/deal', 'OrderController@addDeal')->name('orders.add.deal');

        Route::post('/orders/add/statusForFind', 'OrderController@addStatusToOrderFind')->name('orders.add.statusForFind');

        Route::post('/orders/add/comment', 'OrderController@addCommentToOrdersObj')->name('orders.add.comment');
        Route::post('/orders/edit/comment', 'OrderController@editCommentToOrdersObj')->name('orders.edit.comment');
        Route::post('/orders/addlist', 'OrderController@addListObj')->name('orders.addlist');


        Route::post('/{model_type}/responsible-check/check-duplicates', 'ResponsibleCheckController@checkDuplicates')->name('responsibleCheck.checkDuplicates');
    });

});




// Управление пользователями (plus settings)
Route::get('/access',function (){
    return view('access.access');
});

Route::prefix('bitrix')->name('bitrix.')->group(function (){
    Route::any('add-client-crm','UsContactsController@add_client_on_crm');
    Route::any('update-client-crm','UsContactsController@update_client_on_crm');
    Route::any('delete-client-crm','UsContactsController@delete_client_on_crm');
    //Route::any('client-objects/{id?}','UsContactsController@client_objects')->name('client-objects');
    //Route::any('deal_object','UsDealController@deal_object')->name('deal-object');
    Route::any('update-deal-crm','UsDealController@update_deal_on_crm');
    Route::any('delete-deal-crm','UsDealController@delete_deal_on_crm');
    Route::any('create-deal-crm','UsDealController@create_deal_on_crm');

    Route::any('add-handler-deal-create','UsDealController@create_deal_on_crm_handler');

    Route::any('list-orders','UsOrdersController@listOrder')->name('orders.list');
    Route::get('show-orders/{id}','UsOrdersController@showOrder')->name('orders.show');

    Route::any('update-lead-crm','LeadController@update_lead_on_crm');
    Route::any('update-affair-crm','AffairController@update_affair_on_crm');
    Route::any('update-task-affair-crm','AffairController@update_task_affair_on_crm');
    Route::any('delete-lead-crm','LeadController@delete_lead_on_crm');

});

Route::post('/settingsDictionary/add', 'Ajax\Dict@addVal');
Route::post('/settingsDictionary/edit', 'Ajax\Dict@editVal');
Route::post('/settingsDictionary/delete', 'Ajax\Dict@deleteVal');

Route::post('/settingsStreets/add', 'Ajax\Streets@addVal');
Route::post('/settingsStreets/edit', 'Ajax\Streets@editVal');
Route::post('/settingsStreets/delete', 'Ajax\Streets@deleteVal');





//Route::get('web-presentation',function (){
//    return view ('web_presentation.list');
//});
//
//Route::get('web-presentation/single',function (){
//    return view('web_presentation.show');
//});



Route::get('/test','RoleAndPermissionController@index');

Route::prefix('web-presentation')->name('web-presentation.')->group(function(){
    Route::get('/','WebPresentationController@index')->name('index');
    Route::get('single/{id}','WebPresentationController@show')->name('show');
    Route::get('single/{id}/{links}','WebPresentationController@showSingleByLink')->name('single_links');
    Route::post('send_notific','WebPresentationController@send_notific')->name('send_notific');
    Route::post('get_hash_link','WebPresentationController@getHashLink')->name('get_hash_link');
    Route::get('web/{links}','WebPresentationController@showByLink')->name('links');
});

Route::prefix('share')->name('share.')->group(function(){
    Route::get('{link}','ShareController@show')->name('show');
});

Route::get('add-type','FrontController@add_info');










//Формирование yrl
Route::get('/create/yrl/{object_type}/{site}', 'ExportController@create_yrl')->name('create.yrl');

Route::get('/cron/getCurrentPercent', 'ExportController@getCurrentPercent')->name('check_status');
Route::get('/cron/getCurrentLead', 'LeadController@getCurrentLead')->name('check_status_lead');

Route::get('test-mail', 'SendMailController@sendMail');

Auth::routes([
    'register' => false,
    'verify' => false,
    'forget' => false,
    'reset' =>false
]);

