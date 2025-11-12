<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes (no authentication required)
Route::post('register', 'AuthController@register');
Route::post('login', 'AuthController@login');

// Protected routes (need Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', 'AuthController@logout');
    Route::get('me', 'AuthController@me');
});