<?php

namespace Bilaliqbalr\LaravelRedis\Contracts;


interface Model
{
    public function prefix();

    public function getColumnKey(...$arguments);

    public function getNextId();
}
