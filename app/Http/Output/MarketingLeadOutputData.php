<?php

declare(strict_types=1);

namespace App\Http\Output;

use App\Models\MarketingLead;
use App\UseCases\Contracts\OutputData;
use Illuminate\Database\Eloquent\Model;

readonly class MarketingLeadOutputData implements OutputData
{
    public function transform(Model|MarketingLead $model): array
    {
        return [
            'id' => $model->id,
            'clueTime' => $model->clue_time->format('Y-m-d H:i:s'),
            'username' => $model->username,
            'phone' => $model->phone,
            'searchWord' => $model->search_word,
            'keyword' => $model->keyword,
        ];
    }
}
