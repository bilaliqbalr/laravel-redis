<?php

namespace Bilaliqbalr\LaravelRedis\Models;


use Bilaliqbalr\LaravelRedis\Contracts\Model as ModelContract;
use Bilaliqbalr\LaravelRedis\Support\HasRelation;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class Model implements ModelContract, Arrayable, Jsonable
{
    use HasAttributes,
        HidesAttributes,
        GuardsAttributes,
        HasTimestamps,
        HasRelation;

    /**
     * This is the default format key against which model data will be stored in redis
     */
    public const ID_KEY = "{model}:%d";

    protected $incrementing = false;

    /**
     * List of all fields to make model searchable on, this way while creating model,
     * it will store model id against those field values as a key value pair
     * where key must be the field name while value will be the redis key format
     *
     * @var array
     */
    protected $searchBy = [];

    /**
     * Redis connection name as defined in config/database.php
     *
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
     * Create a new Redis model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->setConnectionName($this->connection ?? config('laravel-redis.connection'));

        $this->fill($attributes);
    }

    /**
     * @param string $connection
     */
    public function setConnectionName(string $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return string
     */
    public function getConnectionName() : string
    {
        return $this->connection;
    }

    /**
     * Return redis connection object
     *
     * @return Connection
     */
    public function getConnection() : Connection
    {
        return Redis::connection($this->getConnectionName());
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws MassAssignmentException
     */
    public function fill(array $attributes)
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

        // Fill missing fields which are now in fillable but not in record, may be added later once record created
        $missingFields = array_diff($this->getFillable(), array_keys($attributes));
        foreach ($missingFields as $field) {
            $this->setAttribute($field, null);
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function forceFill(array $attributes)
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    public function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /**
     * @param mixed ...$arguments
     * @return string
     */
    public function getColumnKey(...$arguments) : string
    {
        $key = array_shift($arguments);

        return sprintf(str_replace(['{model}:'], [$this->prefix()], $key), ...$arguments);
    }

    /**
     * Return prefix for current model, this prefix will be used
     * while storing any kind of data related to this model
     *
     * @return string
     */
    public function prefix() : string
    {
        return Str::snake(class_basename($this)) . ':';
    }

    /**
     * Return new id for model by maintaining auto-increment in redis environment
     *
     * @return mixed
     */
    protected function getNextId()
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
    public function getKeyName() : string
    {
        return $this->primaryKey;
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey() : string
    {
        return Str::snake(class_basename($this)).'_'.$this->getKeyName();
    }

    /**
     * Qualify the given column name by the model's table.
     *
     * @param  string  $column
     * @return string
     */
    public function qualifyColumn($column) : string
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
    public function qualifyColumns($columns) : array
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
    public function getIncrementing() : bool
    {
        return $this->incrementing;
    }

    /**
     * Set whether IDs are incrementing.
     *
     * @param  bool  $value
     * @return Model
     */
    public function setIncrementing(bool $value) : Model
    {
        $this->incrementing = $value;

        return $this;
    }

    public function getSearchByFields() : array
    {
        return $this->searchBy;
    }

    /**
     * Return searchable column key
     *
     * @param $column
     * @param $value
     * @return string
     */
    public function getSearchColumnKey($column, $value) : string
    {
        return $this->getColumnKey("{model}:{$column}:{$value}");
    }

    /**
     * Create new record in redis database using the provided attributes
     *
     * @param $attributes
     * @return $this
     */
    public function create($attributes)
    {
        // Combining all fields to auto-fill them with at least with null
        $allFields = $this->getFillable() + $this->getHidden() + $this->getGuarded();

        // fill all fields
        $attributes = collect($allFields)->unique()->mapWithKeys(function ($field) use ($attributes) {
            return [$field => $attributes[$field] ?? null];
        })->toArray();

        $attributes = $this->fill($attributes)->getAttributes();

        $newId = $this->getNextId();
        $attributes[$this->getKeyName()] = $newId;

        // Creating searchable fields
        if (!empty($this->searchBy)) {
            foreach ($this->searchBy as $field) {
                // Adding fields to make them searchable
                $this->getConnection()->set(
                    $this->getSearchColumnKey($field, $attributes[$field]),
                    $newId
                );
            }
        }

        // Maintaining timestamps
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

        return $this->newModel($attributes);
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
    public function save()
    {
        // Updating data
        $this->getConnection()->hmset(
            $this->getColumnKey($this::ID_KEY, $this->{$this->getKeyName()}),
            $this->attributes
        );

        $this->exists = true;
    }

    /**
     * This will return all records keys of current model
     *
     * @param bool $idsOnly
     * @return mixed
     */
    public function getAllKeys($idsOnly = false)
    {
        $keys = array_map([$this, 'getKeyWithoutPrefix'], $this->getConnection()->keys(
            str_replace('-1', '*', $this->getColumnKey($this::ID_KEY, -1)) . '[0-9]'
        ));

        if ($idsOnly) {
            $keys = array_map(function($rec) {
                return str_replace($this->prefix(), '', $rec);
            }, $keys);
        }

        return $keys;
    }

    /**
     * @param $value
     * @param null $field
     * @return $this
     */
    public function get($value, $field = null) : ?Model
    {
        $field = is_null($field) ? $this::ID_KEY : $field;
        $data = $this->getConnection()->hgetall($this->getColumnKey($field, $value));

        if (empty($data)) return null;

        $model = $this->newModel($data);
        $model->exists = true;
        return $model;
    }

    public function getKeyWithoutPrefix($key)
    {
        return preg_replace('/^'.config('database.redis.options.prefix').'/', '', $key);
    }

    /**
     * Delete record from redis database by id
     *
     * @param $id
     * @return bool
     */
    public function destroy($id) : bool
    {
        $this->exists = true;

        $model = (new static)->get($id);

        return $model->delete();
    }

    /**
     * Performing delete operation on redis database
     *
     * @return bool
     */
    public function delete() : bool
    {
        // Checking if record exists in model to proceed
        if (!$this->exists) {
            return false;
        }

        $id = $this->getAttribute($this->getKeyName());

        // list of all keys to be deleted
        $keyToDelete = [
            $this->getColumnKey($this::ID_KEY, $id)
        ];

        // Removing searchable data
        if (!empty($this->searchBy)) {
            foreach ($this->searchBy as $field) {
                // Adding fields to make them searchable
                array_push($keyToDelete, $this->getSearchColumnKey($field, $this->getAttribute($field)));
            }
        }

        // Removing relations, not removing relation models
        $matchingKeys = $this->getConnection()->keys(
            $this->getColumnKey($this::ID_KEY, $id) . ':rel:*'
        );
        $matchingKeysToRem = array_map([$this, 'getKeyWithoutPrefix'], $matchingKeys);

        $keyToDelete = array_merge($keyToDelete, $matchingKeysToRem);

        // Deleting all
        $this->getConnection()->del($keyToDelete);

        $this->exists = false;

        return true;
    }

    /**
     * @param null $attributes
     */
    public function newModel($attributes = null) : Model
    {
        return new static($attributes);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray() : array
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
    public function toJson($options = 0) : string
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
        return $this->getAttribute($key);
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
        $this->setAttribute($key, $value);
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
        // Handle static methods based on $searchBy
        if (Str::startsWith($method, 'searchBy')) {
            $class = (new static);
            $field = Str::snake(str_replace('searchBy', '', $method));

            if (!in_array($field, $class->searchBy)) {
                throw new \Exception("Field {$field} not found in 'search by' array.");
            } else {
                $id = $class->getConnection()->get(
                    $class->getSearchColumnKey($field, $parameters[0])
                );

                return $class->get($id);
            }
        }

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
