<?php

namespace App\ThirdParty\Baidu;

use App\Enums\AccountChannel;
use App\Enums\Toggle;
use App\Models\Account;
use App\Models\MarketingLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly final class DeliveryMessage
{
    public function handle(array $message): bool
    {
        if (!$this->passesSign($message)) {
            Log::warning('百度线索推送签名校验失败', [
                'clue_id' => $message['clueId'] ?? null,
            ]);

            return false;
        }

        $clueId = (string)($message['clueId'] ?? '');
        if ($clueId === '') {
            Log::warning('百度线索推送缺少线索ID', ['message' => $message]);

            return false;
        }

        $accounts = $this->enabledAccounts();
        if ($accounts->isEmpty()) {
            Log::warning('百度线索推送没有可用账户', ['clue_id' => $clueId]);

            return false;
        }

        if (MarketingLead::query()->where('clue_id', $clueId)->exists()) {
            return true;
        }

        if ($this->shouldSkip((string)($message['username'] ?? ''))) {
            Log::info('百度线索推送命中过滤词，跳过入库', [
                'clue_id' => $clueId,
                'username' => $message['username'] ?? '',
            ]);

            return true;
        }

        $account = $this->nextAccount($accounts);
        $ownerIds = $account->users
            ->pluck('id')
            ->values()
            ->all();

        try {
            MarketingLead::query()->create([
                'account_id' => $account->id,
                'owner_id' => $this->nextOwnerId((int)$account->id, $ownerIds),
                'clue_id' => $clueId,
                'username' => $message['username'] ?? '',
                'phone' => $message['phone'] ?? '',
                'keyword' => $message['keyword'] ?? '',
                'search_word' => $message['search_word'] ?? '',
                'clue_time' => $message['clue_time'] ?? date('Y-m-d H:i:s'),
                'site_name' => '',
                'is_faker' => false,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('百度线索推送入库失败', [
                'clue_id' => $clueId,
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function passesSign(array $message): bool
    {
        $configuredSign = (string)config('openapi.baidu_clue_delivery_sign', '');

        return $configuredSign !== ''
            && isset($message['sign'])
            && hash_equals($configuredSign, (string)$message['sign']);
    }

    private function shouldSkip(string $username): bool
    {
        $filterWords = config('openapi.filter_words', config('openapi.filter_keywords', []));

        if (!is_array($filterWords) || $username === '') {
            return false;
        }

        foreach ($filterWords as $word) {
            $word = trim((string)$word);

            if ($word !== '' && str_contains($username, $word)) {
                return true;
            }
        }

        return false;
    }

    private function enabledAccounts(): Collection
    {
        return Account::query()
            ->with(['users' => fn($query) => $query->select('users.id')->orderBy('users.id')])
            ->where('channel', AccountChannel::BAIDU->value)
            ->where('status', Toggle::ENABLED->value)
            ->orderBy('id')
            ->get();
    }

    private function nextAccount(Collection $accounts): Account
    {
        $cursor = $this->nextCursor('baidu-delivery-account-cursor');

        return $accounts->values()->get($cursor % $accounts->count(), $accounts->first());
    }

    private function nextOwnerId(int $accountId, array $ownerIds): ?int
    {
        if (empty($ownerIds)) {
            return null;
        }

        $cursor = $this->nextCursor("baidu-delivery-account-{$accountId}-owner-cursor");

        return $ownerIds[$cursor % count($ownerIds)];
    }

    private function nextCursor(string $key): int
    {
        Cache::add($key, 0);

        $cursor = Cache::increment($key);

        if (!is_int($cursor) || $cursor < 1) {
            Cache::put($key, 1);

            return 0;
        }

        return $cursor - 1;
    }
}
