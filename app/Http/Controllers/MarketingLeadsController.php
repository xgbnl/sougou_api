<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Output\MarketingLeadOutputData;
use App\Http\Requests\MarketingLeadRequest;
use App\Models\User;
use App\UseCases\Interactor\MarketingLeadInteractor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'clue_time',
            'site_name',
            'username',
            'phone',
            'search_word',
            'keyword',
        ])
            ->toViewData(new MarketingLeadOutputData($user->role));
    }

    public function import(Request $request, #[CurrentUser] User $user, MarketingLeadInteractor $useCase): string
    {
        $inputData = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'accountIds' => 'required|array|min:1',
            'accountIds.*' => 'integer',
        ]);

        $useCase->importMarketingLeads($user, $inputData['file'], $inputData['accountIds']);

        return '导入成功';
    }

    public function export(#[CurrentUser] User $user, MarketingLeadInteractor $useCase): BinaryFileResponse
    {
        $filePath = $useCase->exportMarketingLeads($user);

        return response()
            ->download(
                $filePath,
                '线索数据-' . date('YmdHis') . '.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )
            ->deleteFileAfterSend();
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
