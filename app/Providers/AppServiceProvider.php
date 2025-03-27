<?php

namespace App\Providers;

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
        $this->app['router']->aliasMiddleware('feature', \App\Http\Middleware\CheckSubscriptionFeature::class);
        $this->app['router']->aliasMiddleware('limit', \App\Http\Middleware\CheckResourceLimit::class);
    }
}
