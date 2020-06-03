<?php



Route::prefix('dictionary')->group(function (){

    Route::get('/','DictionaryController@index');

    Route::get('/{name}','DictionaryController@getDictionary');

});

Route::prefix('address')->group(function (){

    Route::get('/','AddressController@index');

    Route::get('/country','AddressController@getCountry');

    Route::get('/region/{countryId}','AddressController@getRegion');

    Route::get('/area/{regionId}','AddressController@getArea');

    Route::get('/city/{areaId}','AddressController@getCity');

    Route::get('/district/{cityId}','AddressController@getDistrict');

    Route::get('/microarea/{cityId}','AddressController@getMicroArea');

    Route::get('/landmark/{cityId}/{microarea_id?}','AddressController@getLandmark');

    Route::get('/street/{cityId}','AddressController@getStreets');

});

Route::get('objects','ObjectsController@index');
