<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Bilaliqbalr\LaravelRedis\Contracts\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class Relation
{
    /**
     * @var Model
     */
    private $current;
    /**
     * @var Model
     */
    private $related;
    /**
     * @var null
     */
    private $foreignKey;
    /**
     * @var null
     */
    private $localKey;

    public function __construct(Model $current, Model $related, $foreignKey = null, $localKey = null)
    {
        $this->current = $current;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return Str::snake(class_basename($this)).'_'.$this->current->getKeyName();
    }

    /**
     * Get the redis key for relationship
     * @return string
     */
    public function getRelationKey()
    {
        $localKey = $this->localKey ?: $this->current->getKeyName();
        $foreignPrefix = rtrim($this->related->qualifyColumn(""), ':');

        return "{$this->current->qualifyColumn($this->{$localKey})}:rel:{$foreignPrefix}";
    }

    /**
     * Sync or relate given model with current one
     */
    public function sync()
    {
        // Creating relation
        $this->current->redis->zadd(
            $this->getRelationKey(),
            $this->related->{$this->foreignKey}
        );
    }

    /**
     * Unlink the relation
     */
    public function detach()
    {
        // Removing relation
        $this->current->redis->zrem(
            $this->getRelationKey(),
            $this->related->{$this->foreignKey}
        );
    }

    /**
     * @param null $offset
     * @param null $limit
     * @return mixed
     */
    public function getItems($offset = null, $limit = null)
    {
        if (is_null($offset)) {
            return $this->current->redis->zrange(
                $this->getRelationKey()
            );
        }

        return $this->current->redis->zrange(
            $this->getRelationKey(), '1', '+inf', 'BYSCORE', 'LIMIT', $offset, $limit
        );
    }

    /**
     * @param null $perPage
     * @param null $currentPage
     * @return array
     */
    public function get($perPage = null, $currentPage = null)
    {
        $relatedItems = [];

        foreach ($this->getItems($currentPage, $perPage) as $id) {
            $relatedItems[] = $this->related->get($id);
        }

        return $relatedItems;
    }

    /**
     * @param $perPage
     * @param null $currentPage
     * @param array $options
     * @return LengthAwarePaginator
     */
    public function paginate($perPage, $currentPage = null, array $options = [])
    {
        $currentPage = $currentPage ?? 0;

        $items = $this->get($perPage, $currentPage);
        $total = $this->related->redis->zcard($this->getRelationKey());

        return new LengthAwarePaginator($items, $total, $perPage, $currentPage, $options);
    }
}
