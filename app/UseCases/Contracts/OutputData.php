<?php

namespace App\UseCases\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OutputData
{
    public function transform(Model $model): array;
}
