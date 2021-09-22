<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

trait BaseModel
{
    protected $connection;

    /**
     * @param mixed ...$arguments
     * @return string
     */
    public static function getColumnKey(...$arguments)
    {
        $key = array_shift($arguments);

        return sprintf($key, $arguments);
    }

    /**
     * Return prefix for current model
     *
     * @return string
     */
    public function prefix()
    {
        return Str::snake(get_class($this)) . ':';
    }

    /**
     * Get next id of current model
     *
     * @return mixed
     */
    public function getNextId()
    {
        $totalRecordsKey = 'total_' . $this->prefix();

        if ( ! Redis::connection($this->connection)->exists($totalRecordsKey)) {
            Redis::connection($this->connection)->set($totalRecordsKey, 0);
        }

        return Redis::connection($this->connection)->incr($totalRecordsKey);
    }
}
