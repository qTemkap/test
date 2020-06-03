<?php

Route::prefix('land')->name('objectApi.land.')->group(function(){
    Route::post('/{object}/responsible/update','LandUSController@updateResponsible')->name('responsible.update');
});

Route::prefix('private-house')->name('objectApi.privateHouse.')->group(function(){
    Route::post('create','PrivateHouseUSController@create')->name('create');
    Route::post('file','PrivateHouseUSController@uploadFile')->name('upload_photo');
    Route::post('file-delete','PrivateHouseUSController@deleteFile')->name('file_delete');
    Route::post('file-plan','PrivateHouseUSController@uploadFilePlan')->name('upload_photo_plan');
    Route::post('file-delete-plan','PrivateHouseUSController@deleteFilePlan')->name('file_delete_plan');
    Route::post('file-base','PrivateHouseUSController@createFile')->name('file_base');
    Route::post('update','PrivateHouseUSController@update')->name('update');
    Route::post('update_address','PrivateHouseUSController@update_address')->name('update_address');
    Route::get('delete/{id}','PrivateHouseUSController@delete_obj')->name('delete');
    Route::post('get_history_price','PrivateHouseUSController@get_history_price')->name('history_price');
    Route::post('fast_update','PrivateHouseUSController@fast_update')->name('fast_update');
    Route::post('zeroingCallEvent','PrivateHouseUSController@zeroingCallEvent')->name('zeroingCallEvent');

    Route::post('/{object}/responsible/update','PrivateHouseUSController@updateResponsible')->name('responsible.update');
});

Route::prefix('commerce')->name('objectApi.commerce.')->group(function(){
    Route::post('create','CommerceUSController@create')->name('create');
    Route::post('file','CommerceUSController@uploadFile')->name('upload_photo');
    Route::post('file-delete','CommerceUSController@deleteFile')->name('file_delete');
    Route::post('file-plan','CommerceUSController@uploadFilePlan')->name('upload_photo_plan');
    Route::post('file-delete-plan','CommerceUSController@deleteFilePlan')->name('file_delete_plan');
    Route::post('file-base','CommerceUSController@createFile')->name('file_base');
    Route::post('update','CommerceUSController@update')->name('update');
    Route::get('delete/{id}','CommerceUSController@delete_obj')->name('delete');
    Route::post('get_history_price','CommerceUSController@get_history_price')->name('history_price');
    Route::post('fast_update','CommerceUSController@fast_update')->name('fast_update');
    Route::post('zeroingCallEvent','CommerceUSController@zeroingCallEvent')->name('zeroingCallEvent');
    Route::post('change_house','CommerceUSController@change_house')->name('change_house');
    Route::post('change_address','CommerceUSController@change_address')->name('change_address');

    Route::post('/{object}/responsible/update','CommerceUSController@updateResponsible')->name('responsible.update');
});

Route::prefix('flat')->name('objectApi.flat.')->group(function(){
    Route::post('create','FlatUSController@create')->name('create');
    Route::post('file','FlatUSController@uploadFile')->name('upload_photo');
    Route::post('file-delete','FlatUSController@deleteFile')->name('file_delete');
    Route::post('file-plan','FlatUSController@uploadFilePlan')->name('upload_photo_plan');
    Route::post('file-delete-plan','FlatUSController@deleteFilePlan')->name('file_delete_plan');
    Route::post('file-base','FlatUSController@createFile')->name('file_base');
    Route::post('update','FlatUSController@update')->name('update');
    Route::post('fast_update','FlatUSController@fast_update')->name('fast_update');
    Route::get('delete/{id}','FlatUSController@delete_obj')->name('delete');
    Route::post('get_history_price','FlatUSController@get_history_price')->name('history_price');
    Route::post('change_house','FlatUSController@change_house')->name('change_house');
    Route::post('change_address','FlatUSController@change_address')->name('change_address');
    Route::post('zeroingCallEvent','FlatUSController@zeroingCallEvent')->name('zeroingCallEvent');

    Route::post('/{object}/responsible/update','FlatUSController@updateResponsible')->name('responsible.update');
});

Route::prefix('common')->name('common.')->group(function(){
    Route::get('generate-quick-search','CommonController@quickSearch')->name('quickSearch');
    Route::post('check','CommonController@check_obj')->name('check');
    Route::get('setListObj','CommonController@setListObj')->name('setListObj');
    Route::get('update-landmarks','CommonController@updateLandmark');
    Route::post('media/{mediaType}/{type}/{id}','CommonController@media')->name('media');
    Route::get('test','CommonController@departmentPermission');
    Route::get('photo-fix','CommonController@photoFix');
    Route::post('getComplex','CommonController@getComplex')->name('getComplex');
    Route::post('changeComplex','CommonController@changeComplex')->name('changeComplex');
    Route::get('fixContact','CommonController@fixContact')->name('fixContact');
    Route::get('/{type}/change-address/{id}','CommonController@changeAddress')->name('changeAddress');
    Route::post('change-comment','ObjectCommentController@change')->name('changeComment');
    Route::get('fix-count-rooms','CommonController@fixCountRoom');
    Route::get('getPDFlist_list', 'CommonController@getPDFlist_list')->name('pdf_list_list');
    Route::get('getPDFlist_table', 'CommonController@getPDFlist_table')->name('pdf_list_table');
    Route::get('setObjsForOrders', 'CommonController@setObjsForOrders')->name('setObjsForOrders');
    Route::get('checkContactInOrder', 'CommonController@checkContactInOrder')->name('checkContactInOrder');
    Route::get('fixDuplicate', 'CommonController@fixDuplicate')->name('fixDuplicate');
    Route::post('getAnalog', 'CommonController@getAnalog')->name('getAnalog');
    Route::post('sendMail', 'CommonController@sendMail')->name('sendMail');
    Route::get('setLastAffairDate', 'CommonController@setLastAffairDate')->name('setLastAffairDate');
    Route::post('getClientByID', 'CommonController@getClientByID')->name('getClientByID');
    Route::post('getDoubleStatus', 'CommonController@getDoubleStatus')->name('getDoubleStatus');
});

Route::prefix('houseCatalog')->name('houseCatalog.')->group(function(){
    Route::post('check','HousesCatalogController@checkHouse')->name('check');
    Route::post('map','HousesCatalogController@map')->name('map.check');
    Route::post('create','HousesCatalogController@create')->name('create');
    Route::post('update','HousesCatalogController@update')->name('update');
    Route::post('updateDocumentations','HousesCatalogController@updateDocumentations')->name('updateDocumentations');
    Route::post('get_address','HousesCatalogController@getAddress')->name('getAddress');
    Route::post('get_bc','HousesCatalogController@getBC')->name('get_bc');
    Route::post('saveDocSales','HousesCatalogController@saveDocSales')->name('saveDocSales');
    Route::post('deleteDocSales','HousesCatalogController@deleteDocSales')->name('deleteDocSales');
    Route::get('search','HousesCatalogController@search')->name('search');
    Route::post('saveTypeDoc','HousesCatalogController@saveTypeDoc')->name('saveTypeDoc');
    Route::post('deleteTypeDoc','HousesCatalogController@deleteTypeDoc')->name('deleteTypeDoc');
    Route::post('savePhotoBuilding','HousesCatalogController@savePhotoBuilding')->name('savePhotoBuilding');
    Route::post('deletePhotoBuilding','HousesCatalogController@deletePhotoBuilding')->name('deletePhotoBuilding');
    Route::post('update_markers','HousesCatalogController@updateMarker')->name('update_markers');
    Route::post('file-general-plan','HousesCatalogController@savePhotoGeneralPlan')->name('upload_photo_general_plan')->middleware('can:edit building general plan');
    Route::post('delete-file-general-plan','HousesCatalogController@deletePhotoGeneralPlan')->name('delete_photo_general_plan')->middleware('can:edit building general plan');

    Route::post('upload-photo', 'HousesCatalogController@uploadPhoto')->name('uploadPhoto');
});

Route::prefix('deffered')->name('deffered.')->group(function(){
    Route::post('set_status','DeferredController@setStatus')->name('setStatus');
});