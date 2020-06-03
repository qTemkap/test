<?php

use Illuminate\Http\Request;

Route::group(['middleware' => 'role:manager'], function() {

	Route::post('/products', 'ProductsController@store');
	Route::delete('/products/{id}', 'ProductsController@destroy');

	Route::post('/orders', 'OrdersController@store');

	Route::post('/products/{id}/comments', 'CommentsProductsController@store');
	Route::get('/products/{id}/comments', 'CommentsProductsController@index');

	Route::put('/products/comments/{id}/set-status', 'StatusCommentsController@index');

	Route::put('/products/{id}/cover-image', 'ProductCoverImageController@update');

	Route::post('/products/{id}/wishlist', 'WishlistController@store');

	Route::prefix('/products/{id}/buy/')->group(function() {
		Route::post('/apple-store', 'AppleStoreController@store');

		Route::post('/stripe', 'StripeController@store');

		Route::post('/pay-pal', 'PayPalController@store');
	});
	
});