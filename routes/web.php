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

Route::get('/', 'HomeController@active')->name('active');
Route::get('/updated', 'HomeController@AllUpdated')->name('updated'); 
Route::get('/closed', 'HomeController@Closed')->name('closed'); 
Route::get('/sync', 'HomeController@sync')->name('sync'); 
Route::get('/checkemail', 'HomeController@CheckEmail')->name('checkemail');

Route::get('/data/active', 'HomeController@activeticketdata')->name('activeticketdata');
Route::get('/data/closed', 'HomeController@closedticketdata')->name('closedticketdata');