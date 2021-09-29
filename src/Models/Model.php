<?php

namespace Bilaliqbalr\LaravelRedis\Models;


use Bilaliqbalr\LaravelRedis\Contracts\Model as ModelContract;
use Bilaliqbalr\LaravelRedis\Support\HasRelation;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class Model implements ModelContract
{
    use HasAttributes,
        HidesAttributes,
        GuardsAttributes,
        HasTimestamps,
        HasRelation;

    public const ID_KEY = "{model}:%d";

    protected $incrementing = false;

    protected $searchBy = [];

    /**
     * @var string
     */
    protected $connection;

    /**
     * If current model exists or not
     *
     * @var bool
     */
    protected $exists = false;

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
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->connection = $this->connection ?? config('laravel-redis.connection');

        $this->fill($attributes);
    }

    /**
     * @param $connection
     */
    public function setConnectionName($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return Redis::connection($this->getConnectionName());
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes) : Model
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
        $totalRecordsKey = 'total_' . Str::plural(rtrim($this->prefix(), ':'));

        if ( ! $this->getConnection()->exists($totalRecordsKey)) {
            $this->getConnection()->set($totalRecordsKey, 0);
        }

        return $this->getConnection()->incr($totalRecordsKey);
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
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return Str::snake(class_basename($this)).'_'.$this->getKeyName();
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

        return $this->prefix().$column;
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
     * @return Model
     */
    public function setIncrementing($value)
    {
        $this->incrementing = $value;

        return $this;
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
                $this->getConnection()->set(
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
        $this->getConnection()->hmset(
            $this->getColumnKey($this::ID_KEY, $newId),
            $attributes
        );

        $this->exists = true;

        return new static($attributes);
    }

    /**
     * Update attributes in model
     * @param $attributes
     */
    public function update($attributes)
    {
        if ($this->usesTimestamps()) {
            $attributes[self::UPDATED_AT] = now()->timestamp;
        }

        $this->fill($attributes)->save();
    }

    /**
     * Update model with current attributes
     */
    protected function save()
    {
        $this->getConnection()->hmset(
            $this->getColumnKey($this::ID_KEY, $this->{$this->getKeyName()}),
            $this->attributes
        );

        $this->exists = true;
    }

    /**
     * @param $value
     * @param null $field
     * @return $this
     */
    public function get($value, $field = null)
    {
        $field = is_null($field) ? $this::ID_KEY : $field;
        $data = $this->getConnection()->hgetall($this->getColumnKey($field, $value));

        if (empty($data)) return null;

        $this->exists = true;

        $this->fill($data);

        return $this;
    }

    public function destroy($id)
    {
        $this->exists = true;

        $model = (new static)->get($id);

        return $model->delete();
    }

    public function delete()
    {
        // Checking if record exists in model to proceed
        if (!$this->exists) {
            return false;
        }

        $id = $this->getAttribute($this->getKeyName());

        // Removing searchable data
        if (!empty($this->searchBy)) {
            foreach ($this->searchBy as $field => $format) {
                // Adding fields to make them searchable
                $this->getConnection()->del(
                    $this->getColumnKey($format, $this->getAttribute($field))
                );
            }
        }

        // Removing relations
        $matchingKeys = $this->getConnection()->keys(
            $this->getColumnKey($this::ID_KEY, $id) . ':rel:*'
        );
        $matchingKeysToRem = array_map(function ($key) {
            // removing laravel prefixes
            return ltrim($key, config('database.redis.options.prefix'));
        }, $matchingKeys);
        $this->getConnection()->del($matchingKeysToRem);

        // Removing record
        $this->getConnection()->hdel(
            $this->getColumnKey($this::ID_KEY, $id)
        );

        $this->exists = false;

        return true;
    }

    public function getAttribute($key)
    {
        return $this->attributes[$key];
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     *
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function toJson($options = 0)
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
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

    /**
     * Handle dynamic static method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}
