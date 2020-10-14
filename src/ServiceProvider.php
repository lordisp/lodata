<?php

namespace Flat3\Lodata;

use Flat3\Lodata\Controller\Monitor;
use Flat3\Lodata\Controller\OData;
use Flat3\Lodata\Controller\ODCFF;
use Flat3\Lodata\Controller\PBIDS;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public static function restEndpoint(): string
    {
        return url(self::route()).'/';
    }

    public static function route(): string
    {
        return rtrim(config('odata.route') ?: 'odata', '/');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config.php', 'odata');
    }

    public function boot(Router $router)
    {
        $this->app->singleton(Model::class, function () {
            return new Model();
        });

        Model::discovery();

        $authMiddleware = config('odata.authmiddleware');
        $router->aliasMiddleware('odata.auth', AuthenticateWithBasicAuth::class);

        Route::middleware([$authMiddleware])->group(function () {
            $route = self::route();

            Route::get("{$route}/_lodata/odata.pbids", [PBIDS::class, 'get']);
            Route::get("{$route}/_lodata/{identifier}.odc", [ODCFF::class, 'get']);
            Route::resource("${route}/_lodata/monitor", Monitor::class);

            Route::any("{$route}{path}", [OData::class, 'handle'])
                ->where('path', '(.*)');
        });
    }
}
