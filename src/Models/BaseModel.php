<?php

namespace Bilaliqbalr\LaravelRedis\Models;


use Bilaliqbalr\LaravelRedis\Contracts\Model as ModelContract;
use Bilaliqbalr\LaravelRedis\Support\BaseModel as BaseModelTrait;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class BaseModel implements ModelContract
{
    use BaseModelTrait,
        HidesAttributes,
        GuardsAttributes;

    public const ID_KEY = "%i";

    private $attributes = [];

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

    public function getAttributes() : array
    {
        return $this->attributes;
    }

    public function create($attributes)
    {
        $attributes = $this->fill($attributes)->getAttributes();

        $newId = $this->getNextId();
        $attributes['id'] = $newId;

        if (!empty($this->searchBy)) {
            foreach ($this->searchBy as $field => $format) {
                // Adding fields to make them searchable
                $this->redis->set(
                    $this->getColumnKey($format, $attributes[$field]),
                    $newId
                );
            }
        }

        // Saving user info
        $this->redis->hmset(
            $this->getColumnKey(self::ID_KEY, $newId),
            $attributes
        );

        return $attributes;
    }
}
