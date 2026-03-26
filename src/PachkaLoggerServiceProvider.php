<?php

declare(strict_types=1);

namespace Pachka\Logging;

use Illuminate\Support\ServiceProvider;

class PachkaLoggerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pachka-logger.php', 'pachka-logger');

        $this->loadViewsFrom(__DIR__ . '/Views', 'pachka-logging');

        $this->publishes([
            __DIR__ . '/../config/pachka-logger.php' => config_path('pachka-logger.php'),
        ], 'pachka-logger-config');

        $this->publishes([
            __DIR__ . '/Views' => resource_path('views/vendor/pachka-logging'),
        ], 'pachka-logger-views');
    }
}
