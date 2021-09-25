<?php

namespace Bilaliqbalr\LaravelRedis\Models;


use Bilaliqbalr\LaravelRedis\Contracts\Model as ModelContract;
use Bilaliqbalr\LaravelRedis\Support\BaseModel as RedisBaseModel;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class BaseModel extends RedisBaseModel implements ModelContract
{
    public const ID_KEY = "{model}:%d";

    protected $searchBy = [];

    /**
     * @var Connection
     */
    protected $redis;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->redis = Redis::connection(config('laravel-redis.connection'));

        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes) : BaseModel
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    $key, get_class($this)
                ));
            }
        }

        return $this;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function create($attributes)
    {
        $allFields = $this->getFillable() + $this->getHidden() + $this->getGuarded();

        // fill all attributes
        $attributes = collect($allFields)->unique()->mapWithKeys(function ($field) use ($attributes) {
            return [$field => $attributes[$field] ?? null];
        })->toArray();

        $attributes = $this->fill($attributes)->getAttributes();

        $newId = $this->getNextId();
        $attributes[$this->getKeyName()] = $newId;

        // Creating searchable fields
        if (!empty($this->searchBy)) {
            foreach ($this->searchBy as $field => $format) {
                // Adding fields to make them searchable
                $this->redis->set(
                    $this->getColumnKey($format, $attributes[$field]),
                    $newId
                );
            }
        }

        if ($this->usesTimestamps()) {
            $attributes[self::CREATED_AT] = now()->timestamp;
            $attributes[self::UPDATED_AT] = now()->timestamp;
        }

        // Saving model info
        $this->redis->hmset(
            $this->getColumnKey($this::ID_KEY, $newId),
            $attributes
        );

        return $attributes;
    }

    public function update($attributes)
    {
        if ($this->usesTimestamps()) {
            $attributes[self::UPDATED_AT] = now()->timestamp;
        }

        $this->fill($attributes)->save();
    }

    protected function save()
    {
        $this->redis->hmset(
            $this->getColumnKey($this::ID_KEY, $this->getAttribute($this->getKeyName())),
            $this->attributes
        );
    }

    /**
     * @param $value
     * @param null $field
     * @return $this
     */
    public function get($value, $field = null)
    {
        $field = is_null($field) ? $this::ID_KEY : $field;
        $data = $this->redis->hgetall($this->getColumnKey($field, $value));

        if (empty($data)) return null;

        return new static($data);
    }

    /**
     * Dynamically access the user's attributes.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->attributes[$key];
    }

    /**
     * Dynamically set an attribute on the user.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Dynamically check if a value is set on the user.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Dynamically unset a value on the user.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }
}
