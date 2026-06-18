<?php

declare(strict_types=1);

namespace App\Http\Output;

use App\Models\FormFilter;
use App\UseCases\Contracts\OutputData;
use Illuminate\Database\Eloquent\Model;

readonly final class FormFilterOutputData implements OutputData
{
    public function transform(Model|FormFilter $model): array
    {
        return [
            'type' => $model->type->toViewModel(),
            'createdAt' => $model->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
