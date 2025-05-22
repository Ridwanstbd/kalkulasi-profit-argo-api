<?php

namespace App\Providers;

use App\Models\PriceSchema;
use App\Models\User;
use App\Observers\PriceSchemaObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('role', \App\Http\Middleware\CheckRole::class);
        PriceSchema::observe(PriceSchemaObserver::class);
        User::observe(UserObserver::class);
    }
}
