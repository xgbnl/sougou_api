<?php

namespace App\Console\Commands;

use App\Enums\Toggle;
use App\Models\Account;
use App\Models\MarketingLead;
use App\ThirdParty\Openapi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Throwable;

class SyncMarketingLeadData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-marketing-lead-data
        {--date= : 同步指定日期，格式 YYYY-MM-DD}
        {--start-date= : 同步开始日期，格式 YYYY-MM-DD}
        {--end-date= : 同步结束日期，格式 YYYY-MM-DD}
        {--page-size=500 : 每页数量，最大 500}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步推广线索数据';

    private const int OPENAPI_LIMIT_TIMES = 30;

    private const int OPENAPI_LIMIT_SECONDS = 60;

    private int $requestCount = 0;

    private float $requestWindowStartedAt = 0.0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startedAt = microtime(true);
        $lock = Cache::lock('sync-marketing-lead-data', 300);

        if (!$lock->get()) {
            $this->warn('同步任务正在运行，本次跳过。');
            return self::SUCCESS;
        }

        try {
            [$startDate, $endDate] = $this->dateRange();
            $pageSize = min(500, max(1, (int)$this->option('page-size')));

            $this->info(sprintf('开始同步推广线索: %s ~ %s, pageSize=%d', $startDate, $endDate, $pageSize));

            $summary = [
                'accounts' => 0,
                'requests' => 0,
                'received' => 0,
                'inserted' => 0,
            ];

            Account::query()
                ->where('status', Toggle::ENABLED->value)
                ->orderBy('id')
                ->chunkById(100, function ($accounts) use ($startDate, $endDate, $pageSize, &$summary): void {
                    foreach ($accounts as $account) {
                        $summary['accounts']++;

                        $accountSummary = $this->syncAccount($account, $startDate, $endDate, $pageSize);
                        $summary['requests'] += $accountSummary['requests'];
                        $summary['received'] += $accountSummary['received'];
                        $summary['inserted'] += $accountSummary['inserted'];
                    }
                });

            $elapsed = round(microtime(true) - $startedAt, 3);
            Log::info('推广线索同步完成', $summary + [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'elapsed_seconds' => $elapsed,
                ]);

            $this->info(sprintf(
                '同步完成: accounts=%d, requests=%d, received=%d, inserted=%d, elapsed=%ss',
                $summary['accounts'],
                $summary['requests'],
                $summary['received'],
                $summary['inserted'],
                $elapsed,
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function syncAccount(Account $account, string $startDate, string $endDate, int $pageSize): array
    {
        $startedAt = microtime(true);
        $page = 1;
        $requests = 0;
        $received = 0;
        $inserted = 0;

        try {
            $apiPath = (string)config('openapi.openapi_path');
            $serverUrl = (string)config('openapi.openapi_server_url');

            if ($apiPath === '' || $serverUrl === '') {
                throw new \RuntimeException('openapi 配置缺失，请检查 OPENAPI_SERVER_URL 和 OPENAPI_PATH');
            }

            /** @var Openapi $openapi */
            $openapi = app()->makeWith(Openapi::class, [
                'apiPath' => $apiPath,
                'serverUrl' => $serverUrl,
            ]);

            $openapi
                ->setUserid((int)$account->userid)
                ->setApiSecret((string)$account->secret);

            do {
                $this->throttleOpenapiRequest();

                $payload = $openapi->request($startDate, $endDate, $page, $pageSize);
                $requests++;

                if ((int)($payload['code'] ?? -1) !== 0) {
                    Log::warning('推广线索接口返回失败', [
                        'account_id' => $account->id,
                        'userid' => $account->userid,
                        'page' => $page,
                        'code' => $payload['code'] ?? null,
                        'msg' => $payload['msg'] ?? null,
                    ]);
                    break;
                }

                $data = $payload['data'] ?? [];
                $list = $data['list'] ?? [];
                $total = (int)($data['total'] ?? 0);

                if (empty($list)) {
                    break;
                }

                $received += count($list);
                $inserted += $this->insertMissingLeads((int)$account->id, $list);

                $page++;
            } while (count($list) === $pageSize && (($page - 1) * $pageSize) < $total);
        } catch (Throwable $e) {
            Log::error('推广线索账号同步失败', [
                'account_id' => $account->id,
                'userid' => $account->userid,
                'page' => $page,
                'message' => $e->getMessage(),
            ]);
        }

        Log::info('推广线索账号同步完成', [
            'account_id' => $account->id,
            'userid' => $account->userid,
            'requests' => $requests,
            'received' => $received,
            'inserted' => $inserted,
            'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
        ]);

        return compact('requests', 'received', 'inserted');
    }

    private function insertMissingLeads(int $accountId, array $list): int
    {
        $leadIds = collect($list)
            ->pluck('id')
            ->filter()
            ->map(fn($leadId) => (int)$leadId)
            ->unique()
            ->values();

        if ($leadIds->isEmpty()) {
            return 0;
        }

        $existsLeadIds = MarketingLead::query()
            ->whereIn('lead_id', $leadIds)
            ->pluck('lead_id')
            ->map(fn($leadId) => (int)$leadId)
            ->all();

        $missingLeadIds = array_flip(array_diff($leadIds->all(), $existsLeadIds));
        if (empty($missingLeadIds)) {
            return 0;
        }

        $rows = [];

        foreach ($list as $lead) {
            $leadId = (int)($lead['id'] ?? 0);
            if ($leadId === 0 || !isset($missingLeadIds[$leadId])) {
                continue;
            }

            $rows[] = [
                'account_id' => $accountId,
                'lead_id' => $leadId,
                'customer_name' => $lead['customer_name'] ?? '',
                'customer_tel' => $lead['customer_tel'] ?? '',
                'status' => (int)($lead['status'] ?? 0),
                'data_type' => (int)($lead['data_type'] ?? 0),
                'data_sub_type' => (int)($lead['data_sub_type'] ?? 0),
                'create_time' => $lead['create_time'] ?? date('Y-m-d H:i:s'),
                'site_name' => $lead['site_name'] ?? '',
                'remark' => $lead['remark'] ?? '',
                'ad_trace_id' => $lead['ad_trace_id'] ?? '',
                'ad_source_type' => (int)($lead['ad_source_type'] ?? 0),
                'ad_search_word' => $lead['ad_search_word'] ?? '',
                'ad_keyword' => $lead['ad_keyword'] ?? '',
                'ad_bannerid' => (int)($lead['ad_bannerid'] ?? 0),
                'ip_address' => $lead['ip_address'] ?? '',
                'more_info' => is_array($lead['more_info']) ? json_encode($lead['more_info'], JSON_UNESCAPED_UNICODE) : $lead['more_info'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        try {
            DB::beginTransaction();
            MarketingLead::query()->insertOrIgnore($rows);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('插入线索失败: ' . $e->getMessage());

            return 0;
        }

        return 1;
    }

    private function dateRange(): array
    {
        $date = $this->option('date');
        if (!empty($date)) {
            return [$date, $date];
        }

        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        if (!empty($startDate) || !empty($endDate)) {
            $startDate = $startDate ?: $endDate;
            $endDate = $endDate ?: $startDate;

            return [$startDate, $endDate];
        }

        $today = Carbon::today()->format('Y-m-d');

        return [$today, $today];
    }

    private function throttleOpenapiRequest(): void
    {
        $now = microtime(true);

        if ($this->requestWindowStartedAt === 0.0 || ($now - $this->requestWindowStartedAt) >= self::OPENAPI_LIMIT_SECONDS) {
            $this->requestWindowStartedAt = $now;
            $this->requestCount = 0;
        }

        if ($this->requestCount >= self::OPENAPI_LIMIT_TIMES) {
            $sleepSeconds = (int)ceil(self::OPENAPI_LIMIT_SECONDS - ($now - $this->requestWindowStartedAt));
            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }

            $this->requestWindowStartedAt = microtime(true);
            $this->requestCount = 0;
        }

        $this->requestCount++;
    }
}
