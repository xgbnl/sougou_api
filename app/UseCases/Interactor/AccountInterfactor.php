<?php

declare(strict_types=1);

namespace App\UseCases\Interactor;

use App\Enums\AccountChannel;
use App\Models\Account;
use App\UseCases\Contracts\LengthAwareOutPut;
use App\UseCases\Contracts\OutPutPort;
use App\UseCases\Exceptions\UseCaseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HigherOrderWhenProxy;
use Throwable;

readonly final class AccountInterfactor
{
    /**
     * 创建账号
     * @param array $inputData
     * @return void
     * @throws Throwable
     */
    public function createAccount(array $inputData): void
    {
        $inputData['created_at'] = date('Y-m-d H:i:s');

        if (($inputData['channel'] ?? null) === AccountChannel::BAIDU->value) {
            $exists = Account::query()
                ->where('channel', AccountChannel::BAIDU->value)
                ->where('username', $inputData['username'])
                ->exists();

            if ($exists) {
                throw new UseCaseException('百度账户名已存在');
            }
        }

        try {
            DB::beginTransaction();
            Account::query()
                ->insert($inputData);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('添加账户失败: ' . $e->getMessage());

            throw new UseCaseException('创建失败，请联系管理员');
        }
    }

    /**
     * 获取账号列表
     * @param array $inputData
     * @return OutPutPort
     */
    public function findAccountList(array $inputData): OutPutPort
    {
        $pages = Account::query()
            ->select(['id', 'channel', 'username', 'e_id', 'userid', 'secret', 'status'])
            ->when(isset($inputData['status']), function (Builder|HigherOrderWhenProxy $query) use ($inputData): Builder|HigherOrderWhenProxy {
                return $query->where('status', $inputData['status']);
            })
            ->when(isset($inputData['channel']), function (Builder|HigherOrderWhenProxy $query) use ($inputData): Builder|HigherOrderWhenProxy {
                return $query->where('channel', $inputData['channel']);
            })
            ->orderByDesc('id')
            ->paginate(perPage: $inputData['perPage'], page: $inputData['page']);

        return LengthAwareOutPut::pages($pages);
    }

    /**
     * 编辑状态
     * @param int $id
     * @param int $status
     * @return void
     * @throws Throwable
     */
    public function editStatus(int $id, int $status): void
    {
        try {
            DB::beginTransaction();
            Account::query()
                ->where('id', $id)
                ->update(['status' => $status]);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('状态编辑失败: ' . $e->getMessage());

            throw new UseCaseException('状态变更失败，请联系管理员');
        }
    }
}
