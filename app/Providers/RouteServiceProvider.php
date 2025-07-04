<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {

            Route::middleware('api')
                ->namespace($this->namespace)
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->namespace($this->namespace)
                ->prefix('api/recruitment')
                ->group(base_path('routes/recruitment/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            Route::namespace($this->namespace)
                ->group(base_path('routes/frontend.php'));

            Route::middleware('admin')
                ->namespace($this->namespace)
                ->prefix('recruitment')
                ->group(base_path('routes/recruitment/admin.php'));

            Route::middleware('personnel')
                ->namespace($this->namespace)
                ->prefix('personnel')
                ->group(base_path('routes/personnel/personnel.php'));

            Route::middleware('admin')
                ->namespace($this->namespace)
                ->prefix('plc')
                ->group(base_path('routes/plc/admin.php'));

            Route::middleware('api')
                ->namespace($this->namespace)
                ->prefix('plc')
                ->group(base_path('routes/plc/api.php'));

            Route::middleware('admin')
                ->namespace($this->namespace)
                ->group(base_path('routes/admin.php'));
        });
    }
}
