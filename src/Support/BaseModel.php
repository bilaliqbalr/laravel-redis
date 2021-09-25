<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class BaseModel
{
    use HasAttributes,
        HidesAttributes,
        GuardsAttributes,
        HasTimestamps,
        HasRelation;

    protected $connection;

    protected $incrementing = false;

    /**
     * The name of the "created at" field.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" field.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    protected $primaryKey = "id";

    /**
     * @param mixed ...$arguments
     * @return string
     */
    public function getColumnKey(...$arguments)
    {
        $key = array_shift($arguments);

        return sprintf(str_replace(['{model}:'], [$this->prefix()], $key), ...$arguments);
    }

    /**
     * Return prefix for current model
     *
     * @return string
     */
    public function prefix()
    {
        return Str::snake(class_basename($this)) . ':';
    }

    /**
     * Get next id of current model
     *
     * @return mixed
     */
    public function getNextId()
    {
        $totalRecordsKey = 'total_' . rtrim($this->prefix(), ':');

        if ( ! Redis::connection($this->connection)->exists($totalRecordsKey)) {
            Redis::connection($this->connection)->set($totalRecordsKey, 0);
        }

        return Redis::connection($this->connection)->incr($totalRecordsKey);
    }

    /**
     * Get the primary key.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Qualify the given column name by the model's table.
     *
     * @param  string  $column
     * @return string
     */
    public function qualifyColumn($column)
    {
        if (Str::contains($column, ':')) {
            return $column;
        }

        return $this->prefix().':'.$column;
    }

    /**
     * Qualify the given columns with the model's table.
     *
     * @param  array  $columns
     * @return array
     */
    public function qualifyColumns($columns)
    {
        return collect($columns)->map(function ($column) {
            return $this->qualifyColumn($column);
        })->all();
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return $this->incrementing;
    }

    /**
     * Set whether IDs are incrementing.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setIncrementing($value)
    {
        $this->incrementing = $value;

        return $this;
    }
}
