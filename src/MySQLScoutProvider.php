<?php

namespace Eloquent\MySQLScout;

use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;

class MySQLScoutProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        resolve(EngineManager::class)->extend('mysql', function () {
            return new MySQLScoutEngine();
        });
    }
}
