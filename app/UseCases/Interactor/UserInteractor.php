<?php

declare(strict_types=1);

namespace App\UseCases\Interactor;

use App\Enums\Role;
use App\Enums\Toggle;
use App\Models\Account;
use App\Models\User;
use App\UseCases\Contracts\LengthAwareOutPut;
use App\UseCases\Contracts\OutPutPort;
use App\UseCases\Exceptions\ModelNotFoundException;
use App\UseCases\Exceptions\UseCaseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HigherOrderWhenProxy;
use Throwable;

readonly final class UserInteractor
{
    /**
     * 授权登录
     * @param array $inputData
     * @return array
     */
    public function auth(array $inputData): array
    {
        $user = User::query()->where('username', $inputData['username'])->first();

        if (empty($user)) {
            throw new ModelNotFoundException('用户名或密码错误');
        }

        if (!password_verify($inputData['password'], $user->password)) {
            throw new UseCaseException('用户名或密码错误');
        }

        $token = $user->createToken(
            name: $user->role->isAdmin() ? $user->role->name : 'SUB_ACCOUNT',
            abilities: $user->role->abilities()
        )->plainTextToken;

        return [
            'token' => $token,
            'role' => $user->role->value
        ];
    }

    /**
     * 创建用户
     * @param array $inputData
     * @return void
     * @throws Throwable
     */
    public function createUser(array $inputData): void
    {

        $inputData['role'] = Role::VIEWER->value;
        $inputData['created_at'] = date('Y-m-d H:i:s');

        try {
            DB::beginTransaction();
            User::query()->insert($inputData);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('创建账号失败: ' . $e->getMessage());

            throw new UseCaseException('创建账号失败，请联系管理员');
        }
    }

    /**
     * 获取用户列表
     * @param User $user
     * @param array $inputData
     * @return OutPutPort
     */
    public function findUserList(User $user, array $inputData): OutPutPort
    {
        $pages = User::query()
            ->select(['id', 'username', 'description', 'created_at'])
            ->when(!empty($inputData['username']), function (Builder|HigherOrderWhenProxy $query) use ($inputData): Builder|HigherOrderWhenProxy {
                return $query->where('username', 'like', "%{$inputData['username']}%");
            })
            ->whereNot('id', $user->id)
            ->orderByDesc('id')
            ->paginate(perPage: $inputData['perPage'], page: $inputData['page']);

        return LengthAwareOutPut::pages($pages);
    }

    /**
     * 获取用户可分配账户和已分配账户
     * @param int $userId
     * @return array
     */
    public function findUserAccounts(int $userId): array
    {
        $user = User::query()->find($userId);

        if (empty($user)) {
            throw new ModelNotFoundException('用户不存在');
        }

        $selectedIds = $user->accounts()
            ->pluck('accounts.id')
            ->all();

        $accounts = Account::query()
            ->select(['id', 'username', 'e_id', 'userid'])
            ->where('status', Toggle::ENABLED->value)
            ->orderByDesc('id')
            ->get()
            ->map(fn(Account $account): array => [
                'id' => $account->id,
                'username' => $account->username,
                'eId' => $account->e_id,
                'userid' => $account->userid,
            ])
            ->all();

        return [
            'accounts' => $accounts,
            'selectedIds' => $selectedIds,
        ];
    }

    /**
     * 保存用户线索账户分配
     * @param int $userId
     * @param array $accountIds
     * @return void
     */
    public function syncUserAccounts(int $userId, array $accountIds): void
    {
        $user = User::query()->find($userId);

        if (empty($user)) {
            throw new ModelNotFoundException('用户不存在');
        }

        $enabledAccountIds = Account::query()
            ->where('status', Toggle::ENABLED->value)
            ->whereIn('id', $accountIds)
            ->pluck('id')
            ->toArray();

        $user->accounts()->sync($enabledAccountIds);
    }
}
