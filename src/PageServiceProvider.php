<?php

namespace Webcore\Page;

use Illuminate\Support\ServiceProvider;

class PageServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        require __DIR__.'/routes.php';

        $this->loadViewsFrom(__DIR__.'/views/themes', 'themes');

        $this->publishes([
            __DIR__.'/views/themes' => resource_path('views/vendor/themes'),
        ], 'themes');

        $this->publishes([
            __DIR__.'/views/components' => resource_path('views/components'),
        ], 'components');

        $this->publishes([
            __DIR__.'/views/widgets' => resource_path('views/widgets'),
        ], 'widgets');

        $this->publishes([
            __DIR__.'/assets' => public_path('page-assets'),
        ], 'assets');

        $this->publishes([
            __DIR__.'/config' => config_path('webcore')
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
