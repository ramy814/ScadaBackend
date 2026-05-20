<?php

namespace Mog\ProjectHelper;

use Illuminate\Support\ServiceProvider;

class MogHelpersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mog-helpers.php', 'mog-helpers');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');

        $this->publishes([
            __DIR__.'/../config/mog-helpers.php' => $this->app->basePath('config/mog-helpers.php'),
        ], 'mog-helpers-config');
    }
}
