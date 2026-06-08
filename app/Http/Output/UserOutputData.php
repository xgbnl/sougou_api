<?php

declare(strict_types=1);

namespace App\Http\Output;

use App\Models\User;
use App\UseCases\Contracts\OutputData;
use Illuminate\Database\Eloquent\Model;

readonly final class UserOutputData implements OutputData
{

    public function toViewData(Model|User $model): array
    {
        return [
            'displayName' => $model->display_name,
        ];
    }

    public function makeHidden(): array
    {
        return [
            'display_name',
        ];
    }
}
