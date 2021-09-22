<?php

namespace Bilaliqbalr\LaravelRedis;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Bilaliqbalr\LaravelRedis\LaravelRedis
 */
class LaravelRedisFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-redis';
    }
}
