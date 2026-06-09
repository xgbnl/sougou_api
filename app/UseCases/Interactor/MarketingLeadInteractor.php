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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HigherOrderWhenProxy;
use Vtiful\Kernel\Excel;

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

    public function importMarketingLeads(User $user, UploadedFile $file, array $accountIds): void
    {
        if (!$user->role->isAdmin()) {
            throw new UseCaseException('无导入权限');
        }

        $accounts = Account::query()
            ->where('status', Toggle::ENABLED->value)
            ->whereIn('id', $accountIds)
            ->with(['users' => fn($query) => $query->select('users.id')->orderBy('users.id')])
            ->get();

        if ($accounts->isEmpty()) {
            throw new UseCaseException('请选择可用的线索账户');
        }

        $rows = $this->readImportRows($file);

        if (empty($rows)) {
            throw new UseCaseException('导入文件没有可导入的数据');
        }

        $now = now();
        $insertRows = [];
        $leadIds = [];

        $ownerCursors = [];

        foreach ($rows as $row) {
            foreach ($accounts as $account) {
                $leadId = $this->makeFakeLeadId($leadIds);
                $leadIds[] = $leadId;
                $accountId = (int)$account->id;
                $ownerIds = $account->users
                    ->pluck('id')
                    ->map(fn($id) => (int)$id)
                    ->values()
                    ->all();
                $ownerCursors[$accountId] ??= 0;

                $insertRows[] = [
                    'account_id' => $accountId,
                    'owner_id' => $this->nextOwnerId($ownerIds, $ownerCursors[$accountId]),
                    'lead_id' => $leadId,
                    'customer_name' => $row['customer_name'],
                    'customer_tel' => $row['customer_tel'],
                    'status' => 4,
                    'data_type' => 0,
                    'data_sub_type' => 0,
                    'create_time' => date('Y-m-d H:i:s'),
                    'site_name' => $row['site_name'],
                    'remark' => '',
                    'ad_trace_id' => '',
                    'ad_source_type' => 0,
                    'ad_search_word' => $row['ad_search_word'],
                    'ad_keyword' => $row['ad_keyword'],
                    'ad_bannerid' => 0,
                    'ip_address' => '0.0.0.0',
                    'more_info' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'is_faker' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($insertRows, 500) as $chunk) {
            MarketingLead::query()->insert($chunk);
        }
    }

    public function exportMarketingLeads(User $user): string
    {
        $headers = ['落地页名称', '客户姓名', '客户手机号', '搜索词', '关键词'];
        $data = [];
        $tmpPath = storage_path('app/tmp');

        if (!is_dir($tmpPath)) {
            mkdir($tmpPath, 0755, true);
        }

        $query = MarketingLead::query()
            ->select(['id', 'create_time', 'site_name', 'customer_name', 'customer_tel', 'ad_search_word', 'ad_keyword'])
            ->when(!$user->role->isAdmin(), fn(Builder|HigherOrderWhenProxy $builder): Builder|HigherOrderWhenProxy => $this->scopeAssignedAccounts($builder, $user))
            ->orderByDesc('create_time');

        $query->chunkById(1000, function ($leads) use (&$data): void {
            foreach ($leads as $lead) {
                $data[] = [
                    $lead->site_name,
                    $lead->customer_name,
                    $lead->customer_tel,
                    $lead->ad_search_word,
                    $lead->ad_keyword,
                ];
            }
        });

        return (new Excel(['path' => $tmpPath]))
            ->fileName('marketing-leads-' . uniqid() . '.xlsx')
            ->header($headers)
            ->data($data)
            ->output();
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

        return $query
            ->whereIn('account_id', $accountIds)
            ->where('owner_id', $user->id);
    }

    private function readImportRows(UploadedFile $file): array
    {
        $tmpPath = storage_path('app/tmp');

        if (!is_dir($tmpPath)) {
            mkdir($tmpPath, 0755, true);
        }

        $fileName = 'marketing-leads-import-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $filePath = $tmpPath . DIRECTORY_SEPARATOR . $fileName;

        copy($file->getRealPath(), $filePath);

        try {
            $excel = new Excel(['path' => $tmpPath]);
            $sheetData = $excel
                ->openFile($fileName)
                ->openSheet()
                ->getSheetData();
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

        if (count($sheetData) < 2) {
            return [];
        }

        $headerMap = array_flip(array_map('trim', $sheetData[0]));
        $requiredHeaders = [
            '落地页名称' => 'site_name',
            '客户姓名' => 'customer_name',
            '客户手机号' => 'customer_tel',
            '搜索词' => 'ad_search_word',
            '关键词' => 'ad_keyword',
        ];

        foreach (array_keys($requiredHeaders) as $header) {
            if (!array_key_exists($header, $headerMap)) {
                throw new UseCaseException("导入文件缺少表头：{$header}");
            }
        }

        $rows = [];

        foreach (array_slice($sheetData, 1) as $sheetRow) {
            $row = [];

            foreach ($requiredHeaders as $header => $field) {
                $row[$field] = $sheetRow[$headerMap[$header]] ?? '';
            }

            if (empty(array_filter($row))) {
                continue;
            }

            if (empty($row['site_name']) || empty($row['customer_name']) || empty($row['customer_tel'])) {
                throw new UseCaseException('导入文件存在必填字段为空的数据');
            }

            $row['site_name'] = (string)$row['site_name'];
            $row['customer_name'] = (string)$row['customer_name'];
            $row['customer_tel'] = (string)$row['customer_tel'];
            $row['ad_search_word'] = (string)$row['ad_search_word'];
            $row['ad_keyword'] = (string)$row['ad_keyword'];

            $rows[] = $row;
        }

        return $rows;
    }

    private function nextOwnerId(array $ownerIds, int &$ownerCursor): ?int
    {
        if (empty($ownerIds)) {
            return null;
        }

        $ownerId = $ownerIds[$ownerCursor % count($ownerIds)];
        $ownerCursor++;

        return $ownerId;
    }

    private function makeFakeLeadId(array $except = []): int
    {
        do {
            $leadId = random_int(3000000000, 4294967295);
        } while (in_array($leadId, $except, true) || MarketingLead::query()->where('lead_id', $leadId)->exists());

        return $leadId;
    }
}
