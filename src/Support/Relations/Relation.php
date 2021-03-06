<?php

namespace Bilaliqbalr\LaravelRedis\Support\Relations;


use Bilaliqbalr\LaravelRedis\Contracts\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
     * @var string|null
     */
    private $foreignKey;

    /**
     * @var string|null
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
     * Get the foreign key name for the model.
     *
     * @return string
     */
    protected function getForeignKey() : string
    {
        return $this->foreignKey ?: $this->current->getForeignKey();
    }

    /**
     * Get the local key of current table
     *
     * @return string
     */
    protected function getLocalKey() : string
    {
        return $this->localKey ?: $this->current->getKeyName();
    }

    /**
     * Get the redis key to create relationship
     *
     * @return string
     */
    public function getRelationKey() : string
    {
        $foreignPrefix = rtrim($this->related->qualifyColumn(""), ':');

        return "{$this->current->qualifyColumn($this->current->{$this->getLocalKey()})}:rel:{$foreignPrefix}";
    }

    /**
     * Sync or relate given model with current one
     */
    public function sync()
    {
        // Creating relation
        $this->current->getConnection()->zadd(
            $this->getRelationKey(),
            $this->related->{$this->getLocalKey()}
        );
    }

    /**
     * Removing the relation
     */
    public function detach($ids = null)
    {
        // Removing relation
        if (is_null($ids)) {
            $this->current->getConnection()->del(
                $this->getRelationKey()
            );
        }

        $ids = is_array($ids) ? $ids : [$ids];
        foreach ($ids as $id) {
            $this->current->getConnection()->zrem(
                $this->getRelationKey(),
                $id
            );
        }
    }

    /**
     * Return related model ids
     *
     * @param null $offset
     * @param null $limit
     * @return mixed
     */
    protected function getItems($offset = null, $limit = null, $inReverseOrder = true)
    {
        $order = $inReverseOrder ? ' REV ' : '';
        $limit = !is_null($offset) ? "LIMIT {$offset} {$limit}" : "";

        return $this->current->getConnection()->zrange(
            $this->getRelationKey(), '0', '-1', "BYSCORE {$order} {$limit}"
        );
    }

    /**
     * Fetch and return related model data
     *
     * @param null $perPage
     * @param null $currentPage
     * @return Collection
     */
    public function get($perPage = null, $currentPage = null) : Collection
    {
        $relatedItems = collect([]);

        foreach ($this->getItems($currentPage, $perPage) as $id) {
            $relatedItems->push($this->related->get($id));
        }

        return $relatedItems;
    }

    /**
     * @param $perPage
     * @param null $currentPage
     * @param array $options
     * @return LengthAwarePaginator
     */
    public function paginate($perPage, $currentPage = null, array $options = []) : LengthAwarePaginator
    {
        $currentPage = $currentPage ?? 0;

        $items = $this->get($perPage, $currentPage);
        $total = $this->related->getConnection()->zcard($this->getRelationKey());

        return new LengthAwarePaginator($items, $total, $perPage, $currentPage, $options);
    }

    /**
     * Creating related model based on the relation
     *
     * @param $attributes
     * @return Model
     */
    public function create($attributes) : Model
    {
        // Adding foreign key info
        $attributes[$this->getForeignKey()] = $this->current->{$this->getLocalKey()};

        $createdRec = $this->related->create($attributes);
        $this->related = $createdRec;

        // Creating relation
        $this->sync();

        return $createdRec;
    }
}
