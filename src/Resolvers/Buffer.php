<?php

namespace Awardis\Federation\Resolvers;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class Buffer
 * @package Awardis\Federation\Resolvers
 */
class Buffer {

    /**
     * @var array
     */
    protected array $buffers = [];

    /**
     * @var array
     */
    protected array $loaded = [];

    /**
     * @param string $model
     * @param array $values
     */
    public function add(string $model, array $values) {
        if ( ! isset($this->buffers[$model])) {
            $this->buffers[$model] = [];
        }

        $this->buffers[$model] []= $values;
    }

    /**
     * @param string $model
     */
    public function load(string $model) {
        if (isset($this->loaded[$model])) {
            return;
        }

        $query = $model::query();
        foreach ($this->buffers[$model] as $constraints) {
            $query->orWhere(function (Builder $query) use ($constraints) {
                foreach ($constraints as $key => $value) {
                    $query->where($key, $value);
                }
            });
        }

        $models = $query->get();

        $this->loaded[$model] = $models;
    }

    /**
     * @param string $model
     * @param        $value
     *
     * @return mixed
     */
    public function get(string $model, array $values) {
        return $this->loaded[$model]->first(function ($loaded) use ($values) {
            foreach ($values as $key => $value) {
                if ($loaded->{$key} !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

}
