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
                    $this->getColumnKey($this->prefix() . $format, $attributes[$field]),
                    $newId
                );
            }
        }

        // Saving model info
        $this->redis->hmset(
            $this->getColumnKey($this::ID_KEY, $newId),
            $attributes
        );

        return $attributes;
    }
}
