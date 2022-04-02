<?php

/*
 *
 *
 * (c) Allen, Li <morningbuses@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Goodcatch\FXK\Laravel;

use Goodcatch\FXK\FXK;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

/**
 * Class ServiceProvider
 * @package Goodcatch\FXK\Laravel
 */
class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->configure();
        $this->offerPublishing();
        $this->registerServices();

    }

    /**
     * Register config.
     */
    protected function configure()
    {

        $this->mergeConfigFrom(
            __DIR__.'/../../config/fxiaoke.php', 'fxiaoke'
        );

    }

    /**
     * Setup the resource publishing groups for Fxiaoke.
     *
     * @return void
     */
    protected function offerPublishing()
    {

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/fxiaoke.php' => config_path('fxiaoke.php'),
            ], 'fxiaoke-config');
        }
    }

    /**
     * Register Fxiaoke services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        $this->app->singleton('fxiaoke', function ($app) {
            $config = $app->make('config')->get('fxiaoke');
            return new FXK ($config);
        });
    }
}