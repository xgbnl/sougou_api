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
            'createTime' => $model->create_time,
            'siteName' => $model->site_name,
            'customerName' => $model->customer_name,
            'customerTel' => $model->customer_tel,
            'adSearchWord' => $model->ad_search_word,
            'adKeyword' => $model->ad_keyword,
        ];
    }
}
