<?php

namespace Bilaliqbalr\LaravelRedis\Support;


use Illuminate\Support\Str;

trait HasRelation
{
    /**
     * The related model instance.
     *
     * @var \Bilaliqbalr\LaravelRedis\Models\BaseModel
     */
    protected $related;

    /**
     * The foreign key of the related model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The local key of the related model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Get the key value of the related's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->related->getAttribute($this->localKey);
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

    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $this->related = app($related);
        // add key {current}-id-rel-{related}-id

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        dd($localKey, $foreignKey);
    }
}
