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

        $this->loadViewsFrom(__DIR__.'/views', 'page');

        $this->publishes([
            __DIR__.'/views' => resource_path('views/vendor/webcore/page'),
        ], 'views');

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
