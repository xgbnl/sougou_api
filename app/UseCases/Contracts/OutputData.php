<?php

namespace App\UseCases\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OutputData
{
    public function toViewData(Model $model): array;

    public function makeHidden(): array;
}
