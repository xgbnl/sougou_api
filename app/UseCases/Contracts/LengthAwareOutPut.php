<?php

declare(strict_types=1);

namespace App\UseCases\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class LengthAwareOutPut implements OutPutPort
{
    use WithTransform;

    public function __construct(final readonly protected LengthAwarePaginator $paginator)
    {
    }

    public static function pages(LengthAwarePaginator|CursorPaginator $paginator): OutPutPort
    {
        return new LengthAwareOutPut($paginator);
    }

    public function toViewData(OutputData|\Closure|null $ouPutData = null): array
    {
        return [
            'total' => $this->paginator->total(),
            'list' => $this->transform($ouPutData),
        ];
    }
}
