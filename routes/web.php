<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

Route::get('/', 'ApiController@test');

Route::get('/create_root/{key}/{user}/{limit}', 'ApiController@create_root');

Route::get("/create_dir/{key}/{parent}/{dirname}", "ApiController@create_dir");

Route::get('/remove_dir/{key}/{folder}', 'ApiController@rm_dir');

Route::post('/upload_file/{key}/{folder}', [
	'uses' => 'ApiController@add_file',
	'as' => 'file.add'
]);

Route::get('/remove_file/{key}/{file}', 'ApiController@rm_file');

Route::get('/list_folder/{key}/{folder}', 'ApiController@list_folder');

Route::get('/list_file/{key}/{folder}', 'ApiController@list_file');