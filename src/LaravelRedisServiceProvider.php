<?php

namespace Bilaliqbalr\LaravelRedis;

use Bilaliqbalr\LaravelRedis\Commands\RefreshSearchByCommand;
use Bilaliqbalr\LaravelRedis\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Bilaliqbalr\LaravelRedis\Commands\LaravelRedisMakeCommand;

class LaravelRedisServiceProvider extends PackageServiceProvider
{
    private $name = 'laravel-redis';

    public function configurePackage(Package $package): void
    {
        $package
            ->name($this->name)
            ->hasConfigFile($this->name)
            ->hasCommand(LaravelRedisMakeCommand::class)
            ->hasCommand(RefreshSearchByCommand::class);

        // Registering redis custom guard
        Auth::viaRequest($this->getConfig('api-guard'), function (Request $request) {
            return User::searchByApiToken();
        });

        // Registering redis custom user provider
        Auth::provider($this->getConfig('provider'), function ($app, array $config) {
            return new $config['model']();
        });
    }

    public function getConfig($key)
    {
        return config("{$this->name}.{$key}");
    }
}
