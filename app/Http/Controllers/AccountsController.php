<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\UseCases\Interactor\AccountInterfactor;
use Throwable;

readonly final class AccountsController
{
    public function __construct(protected AccountInterfactor $useCase)
    {
    }

    /**
     * 账号列表
     * @param AccountRequest $request
     * @return array
     */
    public function index(AccountRequest $request): array
    {
        $inputData = $request->withScene('index')
            ->withRule('page')
            ->validatedData();

        $outPut = $this->useCase->findAccountList($inputData);

        return $outPut->makeHidden(['e_id'])
            ->toViewData(fn(Account $account): array => [
                'eId' => $account->e_id,
                'status' => $account->status->toViewModel(),
            ]);
    }

    /**
     * 创建账号
     * @throws Throwable
     */
    public function store(AccountRequest $request): string
    {
        $inputData = $request->validatedData();

        $this->useCase->createAccount($inputData);

        return '账号添加成功';
    }

    /**
     * @param int $id
     * @param AccountRequest $request
     * @return string
     * @throws Throwable
     */
    public function update(int $id, AccountRequest $request): string
    {
        $status = $request->validatedData('status');

        $this->useCase->editStatus($id, $status);

        return '操作成功';
    }
}
