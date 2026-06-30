<?php

namespace App\Http\Controllers;

use App\Enums\Toggle;
use App\Models\Account;
use App\Models\MarketingLead;
use App\UseCases\Exceptions\UseCaseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

readonly final class LandingLeadController
{
    public function token(Request $request): JsonResponse
    {
        $this->ensureAllowedSource($request);

        [$left, $right] = [random_int(2, 9), random_int(1, 9)];
        $token = Str::random(48);

        Cache::put($this->tokenKey($token), [
            'answer' => (string)($left + $right),
            'ip' => $request->ip(),
        ], $this->tokenTtl());

        return $this->json($request, [
            'token' => $token,
            'captcha' => "{$left} + {$right} = ?",
        ]);
    }

    public function submit(Request $request): string|JsonResponse
    {
        try {
            $this->ensureAllowedSource($request);
            $this->ensureCleanHoneypot($request);

            $name = trim((string)$request->input('name', $request->input('data.xingming', '')));
            $phone = trim((string)$request->input('phone', $request->input('data.dianhua', '')));

            $this->ensureValidPayload($name, $phone);
            $this->ensureRateLimit($request, $phone);
            $this->ensureValidToken($request);

            if (MarketingLead::query()->where('username', $name)->where('phone', $phone)->exists()) {
                throw new UseCaseException('请勿重复提交', 422);
            }

            $accounts = $this->enabledAccounts();
            if ($accounts->isEmpty()) {
                throw new UseCaseException('提交失败，请稍后重试', 500);
            }

            $owner = $this->nextOwner($accounts);
            if (empty($owner)) {
                throw new UseCaseException('提交失败，请稍后重试', 500);
            }

            MarketingLead::query()->create([
                'account_id' => $owner['account_id'],
                'owner_id' => $owner['user_id'],
                'clue_id' => $this->leadId(),
                'username' => $name,
                'phone' => $phone,
                'keyword' => (string)$request->input('keyword', $request->input('data.lailu', '')),
                'search_word' => (string)$request->input('search_word', ''),
                'clue_time' => now(),
                'site_name' => 'ff-promo',
                'is_faker' => false,
            ]);

            return '提交成功';
        } catch (UseCaseException $e) {
            return $this->fail($request, $e->getMessage(), $e->getCode());
        } catch (Throwable $e) {
            report($e);

            return $this->fail($request, '提交失败，请稍后重试', 500);
        }
    }

    public function options(Request $request): Response
    {
        $this->ensureAllowedSource($request);

        return $this->cors(response('', 204), $request);
    }

    private function ensureAllowedSource(Request $request): void
    {
        if (!$this->isAllowedSource($request)) {
            throw new UseCaseException('非法来源', 403);
        }
    }

    private function isAllowedSource(Request $request): bool
    {
        $allowedOrigins = config('landing.allowed_origins', []);
        if (!is_array($allowedOrigins) || empty($allowedOrigins)) {
            return false;
        }

        $origin = $request->headers->get('Origin');
        if ($origin !== null) {
            return in_array($origin, $allowedOrigins, true);
        }

        $referer = $request->headers->get('Referer');
        if ($referer === null || $referer === '') {
            return false;
        }

        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);
        if ($scheme === null || $host === null) {
            return false;
        }

        $refererOrigin = $scheme . '://' . $host;
        $port = parse_url($referer, PHP_URL_PORT);
        if ($port !== null) {
            $refererOrigin .= ':' . $port;
        }

        return in_array($refererOrigin, $allowedOrigins, true);
    }

    private function ensureCleanHoneypot(Request $request): void
    {
        if (trim((string)$request->input('website', '')) !== '') {
            throw new UseCaseException('提交失败', 422);
        }
    }

    private function ensureValidPayload(string $name, string $phone): void
    {
        if ($name === '' || mb_strlen($name) > 16) {
            throw new UseCaseException('请输入正确姓名', 422);
        }

        if (!preg_match('/^1[3-9]\\d{9}$/', $phone)) {
            throw new UseCaseException('请输入正确手机号', 422);
        }
    }

    private function ensureRateLimit(Request $request, string $phone): void
    {
        $ipKey = 'landing-submit:ip:' . sha1((string)$request->ip());
        $phoneKey = 'landing-submit:phone:' . sha1($phone);

        $this->hitLimit($ipKey, (int)config('landing.ip_limit.max_attempts'), (int)config('landing.ip_limit.decay_seconds'));
        $this->hitLimit($phoneKey, (int)config('landing.phone_limit.max_attempts'), (int)config('landing.phone_limit.decay_seconds'));
    }

    private function hitLimit(string $key, int $maxAttempts, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new UseCaseException('提交太频繁，请稍后再试', 429);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function ensureValidToken(Request $request): void
    {
        $token = (string)$request->input('token', '');
        $captcha = trim((string)$request->input('captcha', ''));
        $payload = Cache::pull($this->tokenKey($token));

        if (!is_array($payload) || $captcha === '') {
            throw new UseCaseException('验证码已失效，请刷新后重试', 422);
        }

        if (($payload['ip'] ?? null) !== $request->ip() || !hash_equals((string)$payload['answer'], $captcha)) {
            throw new UseCaseException('验证码错误', 422);
        }
    }

    private function enabledAccounts(): Collection
    {
        $accountIds = config('landing.account_ids', []);

        return Account::query()
            ->where('status', Toggle::ENABLED->value)
            ->when(!empty($accountIds), fn($query) => $query->whereIn('id', $accountIds))
            ->with(['users' => fn($query) => $query->select('users.id')->orderBy('users.id')])
            ->orderBy('id')
            ->get();
    }

    private function nextOwner(Collection $accounts): array
    {
        $candidates = $accounts
            ->flatMap(fn($account) => $account->users->map(fn($user): array => [
                'user_id' => (int)$user->id,
                'account_id' => (int)$account->id,
            ]))
            ->sortBy([
                ['user_id', 'asc'],
                ['account_id', 'asc'],
            ])
            ->unique('user_id')
            ->values()
            ->all();

        if (empty($candidates)) {
            return [];
        }

        $scope = 'landing-lead-owner-allocation';
        $lock = Cache::lock("{$scope}:lock", 10);

        try {
            $lock->block(5);

            $state = $this->allocationState($scope, $candidates);
            if (empty($state['queue'])) {
                $state['queue'] = $this->rankOwners($state['users']);
            }

            $owner = array_shift($state['queue']);
            foreach ($state['users'] as &$user) {
                if ((int)$user['user_id'] === (int)$owner['user_id']) {
                    $user['count'] = (int)$user['count'] + 1;
                    break;
                }
            }
            unset($user);

            Cache::put($this->allocationKey($scope), $state, Carbon::tomorrow()->addHours(2));

            return $owner;
        } finally {
            try {
                optional($lock)->release();
            } catch (Throwable) {
            }
        }
    }

    private function allocationState(string $scope, array $candidates): array
    {
        $key = $this->allocationKey($scope);
        $state = Cache::get($key);
        $hash = md5(implode('|', collect($candidates)->map(fn(array $candidate) => $candidate['account_id'] . ':' . $candidate['user_id'])->all()));
        $today = Carbon::today()->format('Y-m-d');

        if (!is_array($state) || ($state['date'] ?? null) !== $today || ($state['hash'] ?? null) !== $hash) {
            $ownerIds = collect($candidates)->pluck('user_id')->all();
            $counts = MarketingLead::query()
                ->selectRaw('owner_id, count(*) as total')
                ->whereIn('owner_id', $ownerIds)
                ->whereBetween('clue_time', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
                ->groupBy('owner_id')
                ->pluck('total', 'owner_id');

            return [
                'date' => $today,
                'hash' => $hash,
                'users' => collect($candidates)->map(fn(array $candidate): array => [
                    'user_id' => $candidate['user_id'],
                    'account_id' => $candidate['account_id'],
                    'count' => (int)($counts[$candidate['user_id']] ?? 0),
                ])->values()->all(),
                'queue' => [],
            ];
        }

        return $state;
    }

    private function rankOwners(array $users): array
    {
        return collect($users)
            ->sortBy([
                ['count', 'asc'],
                ['user_id', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function json(Request $request, array $data): JsonResponse
    {
        return $this->cors(response()->json(['code' => 200, 'msg' => 'ok', 'data' => $data]), $request);
    }

    private function cors(Response|JsonResponse $response, Request $request): Response|JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && $this->isAllowedSource($request)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    private function fail(Request $request, string $message, int $status): JsonResponse
    {
        if ($status < 400 || $status > 599) {
            $status = 422;
        }

        return $this->cors(response()->json([
            'code' => $status,
            'msg' => $message,
            'data' => null,
        ], $status), $request);
    }

    private function tokenKey(string $token): string
    {
        return 'landing-token:' . sha1($token);
    }

    private function tokenTtl(): Carbon
    {
        return now()->addSeconds((int)config('landing.token_ttl_seconds', 300));
    }

    private function allocationKey(string $scope): string
    {
        return "{$scope}:state";
    }

    private function leadId(): string
    {
        do {
            $leadId = (string)random_int(3000000000, 4294967295);
        } while (MarketingLead::query()->withTrashed()->where('clue_id', $leadId)->exists());

        return $leadId;
    }
}
