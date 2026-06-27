<?php

declare(strict_types=1);

namespace App\UseCases\Interactor;

use App\Enums\AccountChannel;
use App\Models\MarketingLead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

readonly final class MarketingLeadOwnerAllocator
{
    private const string REGISTRY_KEY = 'marketing-lead-owner-allocation:scopes';

    public function nextForQihuAccount(int $accountId, Collection $users): ?int
    {
        return $this->nextManyForQihuAccount($accountId, $users, 1)[0]['user_id'] ?? null;
    }

    public function nextManyForQihuAccount(int $accountId, Collection $users, int $quantity): array
    {
        $candidates = $users
            ->map(fn($user): array => [
                'user_id' => (int)$user->id,
                'account_id' => $accountId,
            ])
            ->values()
            ->all();

        return $this->nextMany("qihu:account:{$accountId}", AccountChannel::QI_HU, $candidates, $quantity);
    }

    public function nextForBaidu(Collection $accounts): array
    {
        return $this->nextManyForBaidu($accounts, 1)[0] ?? [];
    }

    public function nextManyForBaidu(Collection $accounts, int $quantity): array
    {
        return $this->nextMany('baidu', AccountChannel::BAIDU, $this->accountUserCandidates($accounts), $quantity);
    }

    public function forgetUser(int $userId): void
    {
        $scopes = Cache::get(self::REGISTRY_KEY, []);

        if (!is_array($scopes)) {
            return;
        }

        foreach ($scopes as $scope) {
            $cacheKey = $this->cacheKey((string)$scope);
            $state = Cache::get($cacheKey);

            if (!is_array($state)) {
                continue;
            }

            $state['users'] = array_values(array_filter(
                $state['users'] ?? [],
                fn($user): bool => (int)($user['user_id'] ?? 0) !== $userId
            ));
            $state['queue'] = array_values(array_filter(
                $state['queue'] ?? [],
                fn($user): bool => (int)($user['user_id'] ?? 0) !== $userId
            ));

            Cache::put($cacheKey, $state, $this->ttl());
        }
    }

    private function nextMany(string $scope, AccountChannel $channel, array $candidates, int $quantity): array
    {
        if (empty($candidates) || $quantity < 1) {
            return [];
        }

        $lock = Cache::lock($this->lockKey($scope), 10);

        try {
            $lock->block(5);

            $state = $this->state($scope, $channel, $candidates);

            $owners = [];

            for ($i = 0; $i < $quantity; $i++) {
                if (empty($state['queue'])) {
                    $state['queue'] = $this->rank($state['users']);
                }

                $owner = array_shift($state['queue']);
                $owners[] = $owner;

                foreach ($state['users'] as &$user) {
                    if ($this->sameCandidate($user, $owner)) {
                        $user['count'] = (int)$user['count'] + 1;
                        break;
                    }
                }
                unset($user);
            }

            Cache::put($this->cacheKey($scope), $state, $this->ttl());
            $this->registerScope($scope);

            return $owners;
        } finally {
            optional($lock)->release();
        }
    }

    private function state(string $scope, AccountChannel $channel, array $candidates): array
    {
        $cacheKey = $this->cacheKey($scope);
        $state = Cache::get($cacheKey);
        $candidateHash = $this->candidateHash($candidates);
        $today = Carbon::today()->format('Y-m-d');

        if (
            !is_array($state)
            || ($state['date'] ?? null) !== $today
            || ($state['candidate_hash'] ?? null) !== $candidateHash
        ) {
            return [
                'date' => $today,
                'candidate_hash' => $candidateHash,
                'users' => $this->users($channel, $candidates),
                'queue' => [],
            ];
        }

        return $state;
    }

    private function users(AccountChannel $channel, array $candidates): array
    {
        $userIds = collect($candidates)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $counts = MarketingLead::query()
            ->selectRaw('owner_id, count(*) as total')
            ->whereIn('owner_id', $userIds)
            ->whereBetween('clue_time', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->whereHas('account', fn($query) => $query->where('channel', $channel->value))
            ->groupBy('owner_id')
            ->pluck('total', 'owner_id');

        return collect($candidates)
            ->map(fn(array $candidate): array => [
                'user_id' => (int)$candidate['user_id'],
                'account_id' => (int)$candidate['account_id'],
                'count' => (int)($counts[(int)$candidate['user_id']] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function rank(array $users): array
    {
        return collect($users)
            ->groupBy('count')
            ->sortKeys()
            ->flatMap(function (Collection $group): array {
                $items = $group->values()->all();
                shuffle($items);

                return $items;
            })
            ->values()
            ->all();
    }

    private function accountUserCandidates(Collection $accounts): array
    {
        return $accounts
            ->flatMap(fn($account) => $account->users->map(fn($user): array => [
                'user_id' => (int)$user->id,
                'account_id' => (int)$account->id,
            ]))
            ->values()
            ->all();
    }

    private function sameCandidate(array $left, array $right): bool
    {
        return (int)$left['user_id'] === (int)$right['user_id']
            && (int)$left['account_id'] === (int)$right['account_id'];
    }

    private function candidateHash(array $candidates): string
    {
        $normalized = collect($candidates)
            ->map(fn(array $candidate): string => (int)$candidate['account_id'] . ':' . (int)$candidate['user_id'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        return md5(implode('|', $normalized));
    }

    private function registerScope(string $scope): void
    {
        $scopes = Cache::get(self::REGISTRY_KEY, []);
        $scopes = is_array($scopes) ? $scopes : [];
        $scopes[] = $scope;

        Cache::forever(self::REGISTRY_KEY, array_values(array_unique($scopes)));
    }

    private function cacheKey(string $scope): string
    {
        return "marketing-lead-owner-allocation:{$scope}";
    }

    private function lockKey(string $scope): string
    {
        return "marketing-lead-owner-allocation-lock:{$scope}";
    }

    private function ttl(): Carbon
    {
        return Carbon::tomorrow()->addHours(2);
    }
}
