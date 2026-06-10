<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Output\AccountOutputData;
use App\Http\Requests\AccountRequest;
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
            ->toViewData(new AccountOutputData());
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
     * 更新状态
     * @param int $id
     * @param AccountRequest $request
     * @return string
     * @throws Throwable
     */
    public function update(int $id, AccountRequest $request): string
    {
        $status = $request->withScene('editStatus')->validatedData('status');

        $this->useCase->editStatus($id, $status);

        return '操作成功';
    }
}
