<?php

declare(strict_types=1);

namespace App\UseCases\Interactor;

use App\Enums\Toggle;
use App\Models\Account;
use App\Models\MarketingLead;
use App\Models\User;
use App\UseCases\Contracts\LengthAwareOutPut;
use App\UseCases\Contracts\OutPutPort;
use App\UseCases\Exceptions\UseCaseException;
use Illuminate\Support\Carbon;
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
            ->select(['id', 'create_time', 'site_name', 'customer_name', 'customer_tel', 'ad_search_word', 'ad_keyword'])
            ->when(!$user->role->isAdmin(), fn(Builder|HigherOrderWhenProxy $builder): Builder|HigherOrderWhenProxy => $this->scopeAssignedAccounts($builder, $user))
            ->when(
                value: !empty($inputData['startDate']) && !empty($inputData['endDate']),
                callback: function (Builder|HigherOrderWhenProxy $query) use ($inputData): Builder|HigherOrderWhenProxy {
                    return $query->whereBetween('create_time', [$inputData['startDate'], $inputData['endDate']]);
                })
            ->orderByDesc('create_time')
            ->paginate(perPage: $inputData['perPage'], page: $inputData['page']);

        return LengthAwareOutPut::pages($pages);
    }

    /**
     * 创建手动线索数据
     * @param User $user
     * @param array $inputData
     * @return void
     */
    public function createMarketingLead(User $user, array $inputData): void
    {
        $accountId = $this->firstAccessibleAccountId($user);

        if ($accountId === null) {
            throw new UseCaseException('当前用户没有可用的线索账户');
        }

        MarketingLead::query()->create([
            'account_id' => $accountId,
            'lead_id' => $this->makeFakeLeadId(),
            'customer_name' => $inputData['customerName'],
            'customer_tel' => $inputData['customerTel'],
            'status' => 4,
            'data_type' => 0,
            'data_sub_type' => 0,
            'create_time' => $inputData['createTime'],
            'site_name' => $inputData['siteName'],
            'remark' => '',
            'ad_trace_id' => '',
            'ad_source_type' => 0,
            'ad_search_word' => $inputData['adSearchWord'],
            'ad_keyword' => $inputData['adKeyword'],
            'ad_bannerid' => 0,
            'ip_address' => '0.0.0.0',
            'more_info' => [],
            'is_faker' => 1,
        ]);
    }

    /**
     * 获取 dashboard 统计
     * @param User $user
     * @return array
     */
    public function dashboardStats(User $user): array
    {
        $query = MarketingLead::query();

        if (!$user->role->isAdmin()) {
            $this->scopeAssignedAccounts($query, $user);
        }

        return [
            'totalLeads' => (clone $query)->count(),
            'todayLeads' => (clone $query)
                ->whereBetween('create_time', [
                    Carbon::today()->startOfDay(),
                    Carbon::today()->endOfDay(),
                ])
                ->count(),
        ];
    }

    private function scopeAssignedAccounts(Builder|HigherOrderWhenProxy $query, User $user): Builder|HigherOrderWhenProxy
    {
        $accountIds = $user->accounts()
            ->where('accounts.status', Toggle::ENABLED->value)
            ->pluck('accounts.id')
            ->all();

        if (empty($accountIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('account_id', $accountIds);
    }

    private function firstAccessibleAccountId(User $user): ?int
    {
        if ($user->role->isAdmin()) {
            $accountId = Account::query()
                ->where('status', Toggle::ENABLED->value)
                ->orderBy('id')
                ->value('id');

            return $accountId === null ? null : (int)$accountId;
        }

        $accountId = $user->accounts()
            ->where('accounts.status', Toggle::ENABLED->value)
            ->orderBy('accounts.id')
            ->value('accounts.id');

        return $accountId === null ? null : (int)$accountId;
    }

    private function makeFakeLeadId(): int
    {
        do {
            $leadId = random_int(3000000000, 4294967295);
        } while (MarketingLead::query()->where('lead_id', $leadId)->exists());

        return $leadId;
    }
}
