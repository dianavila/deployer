<?php namespace Grovers\Bbjen;

use Illuminate\Support\ServiceProvider;

class BbjenServiceProvider extends ServiceProvider {

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('bbjen', function() {
            return new \Grovers\Bbjen\bbjen;
        });
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // Set up routing
        include __DIR__.'/routes.php';

        // Published items
        $this->publishes([
            // configuration file
            __DIR__.'/config.php' => config_path('bbjen.php'),

        ]);
    }
}