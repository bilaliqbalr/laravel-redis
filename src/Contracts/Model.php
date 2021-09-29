<?php

namespace Bilaliqbalr\LaravelRedis\Contracts;


interface Model
{
    public function prefix();

    public function getConnection();

    public function getColumnKey(...$arguments);

    public function getNextId();

    public function getForeignKey();

    public function getKeyName();

    public function create($attributes);

    public function qualifyColumn($column);
}
