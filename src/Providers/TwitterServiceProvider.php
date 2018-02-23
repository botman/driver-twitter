<?php

namespace BotMan\Drivers\Twitter\Providers;

use Illuminate\Support\ServiceProvider;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Twitter\TwitterDriver;
use BotMan\Studio\Providers\StudioServiceProvider;

class TwitterServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__.'/../../stubs/twitter.php' => config_path('botman/twitter.php'),
            ]);

            $this->mergeConfigFrom(__DIR__.'/../../stubs/twitter.php', 'botman.twitter');
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(TwitterDriver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
