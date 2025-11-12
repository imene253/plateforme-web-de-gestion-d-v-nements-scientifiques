<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware(['api'])
                ->prefix('api')
                ->group(base_path('routes/api.php'));
                
            // Direct API test route without middleware
            Route::prefix('api')
                ->group(base_path('routes/api_test.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
