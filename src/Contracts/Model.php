<?php

namespace Bilaliqbalr\LaravelRedis\Contracts;


interface Model
{
    public function prefix();

    public static function getColumnKey(...$arguments);

    public function getNextId();
}
