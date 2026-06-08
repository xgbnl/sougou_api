<?php

namespace App\UseCases\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OutPutPort
{
    public static function pages(LengthAwarePaginator|CursorPaginator $paginator): OutPutPort;

    public function toViewData(?OutputData $ouPutData = null): array;
}
