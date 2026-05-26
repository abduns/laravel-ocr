<?php

namespace Dunn\LaravelOcr;

use Dunn\LaravelOcr\Console\Commands\OcrScanCommand;
use Illuminate\Support\ServiceProvider;

final class OcrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ocr.php', 'ocr');

        $this->app->singleton('ocr', function ($app) {
            return new OcrManager(
                config: $app['config'],
                events: $app['events'],
                disks: $app['filesystem'],
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ocr.php' => config_path('ocr.php'),
            ], 'ocr-config');

            $this->commands([OcrScanCommand::class]);
        }
    }
}
