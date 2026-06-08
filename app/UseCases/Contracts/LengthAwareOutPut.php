<?php

namespace App\UseCases\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

final readonly class LengthAwareOutPut implements OutPutPort
{

    public function __construct(final private(set) LengthAwarePaginator $paginator)
    {
    }

    public static function pages(LengthAwarePaginator|CursorPaginator $paginator): OutPutPort
    {
        return new LengthAwareOutPut($paginator);
    }

    public function toViewData(?OutputData $ouPutData = null): array
    {
        $result = [
            'total' => $this->paginator->total(),
        ];

        if (is_null($ouPutData)) {
            $result['list'] = $this->paginator->items();
            return $result;
        }

        $result['list'] = $this->paginator->getCollection()
            ->transform(function (Model $model) use ($ouPutData): array {
                $data = $ouPutData->toViewData($model);

                $model = $model->makeHiddenIf(!empty($ouPutData->makeHidden()), $ouPutData->makeHidden());

                return array_merge($model->toArray(), $data);
            });

        return $result;
    }
}
