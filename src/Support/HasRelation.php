<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Bilaliqbalr\LaravelRedis\Contracts\Model;
use Bilaliqbalr\LaravelRedis\Support\Relations\Relation;

trait HasRelation
{
    /**
     * @param Model $related
     * @param null $foreignKey
     * @param null $localKey
     *
     * @return Relation
     */
    public function relation(Model $related, $foreignKey = null, $localKey = null) : Relation
    {
        return new Relation($this, $related, $foreignKey, $localKey);
    }
}
