<?php

namespace Bilaliqbalr\LaravelRedis\Contracts;


use Illuminate\Redis\Connections\Connection;

interface Model
{
    public function prefix() : string;

    public function getConnection() : Connection;

    public function getColumnKey(...$arguments) : string;

    public function getNextId();

    public function getForeignKey() : string;

    public function getKeyName() : string;

    public function create($attributes);

    public function qualifyColumn($column) : string;
}
