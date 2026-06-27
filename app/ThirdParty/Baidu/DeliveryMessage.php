<?php

namespace App\ThirdParty\Baidu;

use App\Enums\AccountChannel;
use App\Enums\Toggle;
use App\Models\Account;
use App\Models\MarketingLead;
use App\UseCases\Interactor\FormFilterInteractor;
use App\UseCases\Interactor\MarketingLeadOwnerAllocator;
use Illuminate\Support\Collection;
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

        if (MarketingLead::query()->withTrashed()->where('clue_id', $clueId)->exists()) {
            return true;
        }

        /** @var FormFilterInteractor $formFilterInteractor */
        $formFilterInteractor = app(FormFilterInteractor::class);

        if ($formFilterInteractor->shouldSkipName((string)($message['username'] ?? ''))) {
            Log::info('百度线索推送命中过滤词，跳过入库', [
                'clue_id' => $clueId,
                'username' => $message['username'] ?? '',
            ]);

            return true;
        }

        if ($formFilterInteractor->shouldSkipPhone((string)($message['phone'] ?? ''))) {
            Log::info('百度线索推送命中过滤手机号，跳过入库', [
                'clue_id' => $clueId,
                'phone' => $message['phone'] ?? '',
            ]);

            return true;
        }

        /** @var MarketingLeadOwnerAllocator $ownerAllocator */
        $ownerAllocator = app(MarketingLeadOwnerAllocator::class);
        $owner = $ownerAllocator->nextForBaidu($accounts);
        $account = $owner['account_id'] ?? $accounts->first()->id;

        try {
            MarketingLead::query()->create([
                'account_id' => $account,
                'owner_id' => $owner['user_id'] ?? null,
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
                'account_id' => $account,
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

    private function enabledAccounts(): Collection
    {
        return Account::query()
            ->with(['users' => fn($query) => $query->select('users.id')->orderBy('users.id')])
            ->where('channel', AccountChannel::BAIDU->value)
            ->where('status', Toggle::ENABLED->value)
            ->orderBy('id')
            ->get();
    }

}
