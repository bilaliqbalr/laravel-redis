<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Bilaliqbalr\LaravelRedis\Contracts\Model;

trait HasRelation
{
    /**
     * @param Model $related
     * @param null $foreignKey
     * @param null $localKey
     * @return Relation
     */
    public function relation(Model $related, $foreignKey = null, $localKey = null)
    {
        return new Relation($related, $foreignKey, $localKey);
    }
}
