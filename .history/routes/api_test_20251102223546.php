<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Simple test endpoint that doesn't require any controllers
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working!',
        'status' => 'success',
        'timestamp' => now()->toDateTimeString()
    ]);
});