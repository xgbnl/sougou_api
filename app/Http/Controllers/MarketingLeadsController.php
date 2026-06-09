<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Output\MarketingLeadOutputData;
use App\Http\Requests\MarketingLeadRequest;
use App\Models\User;
use App\UseCases\Interactor\MarketingLeadInteractor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

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

        return $output->makeHidden([
            'id',
            'create_time',
            'site_name',
            'customer_name',
            'customer_tel',
            'ad_search_word',
            'ad_keyword',
        ])
            ->toViewData(new MarketingLeadOutputData());
    }

    /**
     * 添加线索数据
     * @param Request $request
     * @param User $user
     * @param MarketingLeadInteractor $useCase
     * @return string
     */
    public function store(Request $request, #[CurrentUser] User $user, MarketingLeadInteractor $useCase): string
    {
        $inputData = $request->validate([
            'createTime' => 'required|date',
            'siteName' => 'required|string|max:255',
            'customerName' => 'required|string|max:255',
            'customerTel' => 'required|string|max:11',
            'adSearchWord' => 'nullable|string|max:255',
            'adKeyword' => 'nullable|string|max:255',
        ]);

        $inputData['adSearchWord'] ??= '';
        $inputData['adKeyword'] ??= '';

        $useCase->createMarketingLead($user, $inputData);

        return '添加成功';
    }

    /**
     * dashboard 线索统计
     * @param User $user
     * @param MarketingLeadInteractor $useCase
     * @return array
     */
    public function stats(#[CurrentUser] User $user, MarketingLeadInteractor $useCase): array
    {
        return $useCase->dashboardStats($user);
    }
}
