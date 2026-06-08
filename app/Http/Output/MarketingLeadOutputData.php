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
            'campaignId' => $model->campaign_id,
            'campaignName' => $model->campaign_name,
            'groupId' => $model->group_id,
            'groupName' => $model->group_name,
            'createTime' => $model->create_time,
        ];
    }
}
