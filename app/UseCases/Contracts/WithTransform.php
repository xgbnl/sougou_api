<?php

namespace App\UseCases\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait WithTransform
{
    protected array $makeHidden = [];

    protected function transform(OutputData|\Closure|null $ouPutData = null): Collection|array
    {
        $collection = $this->paginator->getCollection();

        if (is_null($ouPutData)) {
            return empty($this->makeHidden)
                ? $collection
                : $collection->map(fn(Model $model): Model => $model->makeHidden($this->makeHidden));
        }

        if ($ouPutData instanceof \Closure) {
            return empty($this->makeHidden)
                ? $collection->transform(fn(Model $model): array => array_merge($model->toArray(), $ouPutData($model)))
                : $collection->transform(fn(Model $model): array => array_merge($model->makeHidden($this->makeHidden)->toArray(), $ouPutData($model)));
        }

        return empty($this->makeHidden)
            ? $collection->transform(fn(Model $model): array => array_merge($model->toArray(), $ouPutData->transform($model)))
            : $collection->transform(fn(Model $model): array => array_merge($model->makeHidden($this->makeHidden)->toArray(), $ouPutData->transform($model)));
    }

    public function makeHidden(array $makeHidden): OutPutPort
    {
        $this->makeHidden = $makeHidden;

        return $this;
    }
}
