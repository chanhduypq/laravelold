<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth'])->group(function() {

});

Route::middleware(['web'])->group(function() {
    Route::get('/', [
        'as' => 'home.index',
        'uses' => 'HomeController@index'
    ]);
});

Route::get('/logout', function () {
    Auth::logout();
    return redirect()->route('home.index');
});

Auth::routes();
