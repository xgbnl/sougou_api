<?php

declare(strict_types=1);

namespace App\UseCases\Interactor;

use App\Models\MarketingLead;
use App\Models\User;
use App\UseCases\Contracts\LengthAwareOutPut;
use App\UseCases\Contracts\OutPutPort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HigherOrderWhenProxy;

readonly final class MarketingLeadInteractor
{
    /**
     * 获取营销线索列表
     * @param User $user
     * @param array $inputData
     * @return OutPutPort
     */
    public function findMarketingLeadList(User $user, array $inputData): OutPutPort
    {
        $pages = MarketingLead::query()
            ->select(['id', 'campaign_id', 'campaign_name', 'group_name', 'group_id', 'gender', 'phone', 'create_time'])
            ->when(
                value: !$user->role->isAdmin(),
                callback: function (Builder|HigherOrderWhenProxy $builder) use ($user): Builder|HigherOrderWhenProxy {
                    return $builder->where('user_id', $user->id);
                })
            ->when(
                value: !empty($inputData['startDate']) && !empty($inputData['endDate']),
                callback: function (Builder|HigherOrderWhenProxy $query) use ($inputData): Builder|HigherOrderWhenProxy {
                    return $query->whereBetween('create_time', [$inputData['startDate'], $inputData['endDate']]);
                })
            ->orderByDesc('create_time')
            ->paginate(perPage: $inputData['perPage'], page: $inputData['page']);

        return LengthAwareOutPut::pages($pages);
    }
}
