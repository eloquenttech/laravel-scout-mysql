<?php

namespace Eloquent\MySQLScout;

use Illuminate\Database\Connection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Collection;

class MySQLScoutEngine extends Engine
{
    /**
     * Update the given model in the index.
     *
     * @param  Collection $models
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $model = $models->first();
        $indexTable = $model->searchableAs();
        $connection = $model->getConnection();

        if (!$connection->getSchemaBuilder()->hasTable($indexTable)) {
            $connection->getSchemaBuilder()->create($indexTable, function (Blueprint $table) use ($model) {
                if (config('scout.mysql.uuids')) {
                    $table->uuid($model->getForeignKey())->primary();
                } else {
                    $table->bigInteger($model->getForeignKey())->primary();
                }

                $table->text('index')->nullable();

                if (method_exists($model, 'searchableProperties')) {
                    foreach ($model->searchableProperties() as $field => $property) {
                        if ($property['type'] === 'integer') {
                            $table->integer($field)->nullable()->index();
                        } else if ($property['type'] === 'date') {
                            $table->dateTime($field)->nullable()->index();
                        } else if ($property['type'] === 'boolean') {
                            $table->boolean($field)->nullable()->index();
                        } else if ($property['type'] === 'keyword') {
                            $table->string($field)->nullable()->index();
                        }
                    }
                }
            });

            if (config('scout.mysql.mode') === 'FULLTEXT') {
                $connection->unprepared("ALTER TABLE $indexTable ADD FULLTEXT(`index`)");
            }
        }

        $models->each(function ($model) use ($connection, $indexTable) {
            $searchableParams = $model->toSearchableArray();

            $where = [$model->getForeignKey() => $model->getScoutKey()];
            $values = ['index' => json_encode($searchableParams)];

            if (method_exists($model, 'searchableProperties')) {
                foreach ($model->searchableProperties() as $field => $property) {
                    if (in_array($property['type'], ['integer', 'date', 'boolean', 'keyword'])) {
                        $values[$field] = $searchableParams[$field];
                    }
                }
            }

            $connection->table($indexTable)->updateOrInsert($where, $values);
        });
    }

    /**
     * Remove the given model from the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $model = $models->first();
        $indexTable = $model->searchableAs();
        $connection = $model->getConnection();

        $connection->table($indexTable)
            ->whereIn($model->getForeignKey(), $models->map->getScoutKey()->toArray())
            ->delete();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, [
            'filters' => $this->filters($builder),
            'size' => $builder->limit,
        ]);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'filters' => $this->filters($builder),
            'from' => ($page * $perPage) - $perPage,
            'size' => $perPage,
        ]);

       $result['nbPages'] = $result->count() / $perPage;

        return $result;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return $results->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results->count() === 0) {
            return collect([]);
        }

        return $model->getScoutModelsByIds($builder, $results->pluck('_id')->toArray());
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results->count();
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $model->chunk(250, function ($models) {
            $this->delete($models);
        });
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $indexTable = $builder->model->searchableAs();
        $connection = $builder->model->getConnection();

        $query = $connection->table($indexTable)->selectRaw($builder->model->getForeignKey() . ' as _id');

        if (config('scout.mysql.mode') === 'FULLTEXT') {
            $query->whereRaw('MATCH(`index`) AGAINST(?)', [$builder->query]);
        } else {
            $query->where('index', 'LIKE', '%' . $builder->query . '%');
        }

        if ($sort = $this->sort($builder)) {
            foreach ($sort as $field => $direction) {
                $query->orderBy($field, $direction);
            }
        }

        if ($options['filters']) {
            foreach ($options['filters'] as $field => $value) {
                if (is_array($value)) {
                    $query->where(...$value);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        if (isset($options['from'])) {
            $query->skip($options['from']);
        }

        if ($options['size']) {
            $query->limit($options['size']);
        }

        if ($options['filters']) {
            foreach ($options['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        return $query->get();
    }

    /**
     * Get the filter array for the query.
     *
     * @param  Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->mapWithKeys(function ($value, $key) {
            return [$key => $value];
        })->toArray();
    }

    /**
     * Generates the sort if theres any.
     *
     * @param  Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (!count($builder->orders)) {
            return null;
        }

        return collect($builder->orders)->mapWithKeys(function($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }
}
