<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Output\MarketingLeadOutputData;
use App\Http\Requests\MarketingLeadRequest;
use App\Models\User;
use App\UseCases\Interactor\MarketingLeadInteractor;
use Illuminate\Container\Attributes\CurrentUser;

readonly final class MarketingLeadsController
{
    /**
     * 营销线索列表
     * @param MarketingLeadRequest $request
     * @param User $user
     * @param MarketingLeadInteractor $useCase
     * @return array
     */
    public function index(MarketingLeadRequest $request, #[CurrentUser] User $user, MarketingLeadInteractor $useCase): array
    {
        $output = $useCase->findMarketingLeadList($user, $request->validated());

        return $output->makeHidden(['campaign_id', 'campaign_name', 'group_id', 'group_name', 'create_time'])
            ->toViewData(new MarketingLeadOutputData());
    }
}
