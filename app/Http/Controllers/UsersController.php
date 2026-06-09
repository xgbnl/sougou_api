<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\UseCases\Interactor\UserInteractor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Throwable;

readonly final class UsersController
{

    public function __construct(protected UserInteractor $useCase)
    {
    }

    /**
     * 用户列表
     * @param UserRequest $request
     * @return array
     */
    public function index(UserRequest $request,#[CurrentUser]User $user): array
    {
        $inputData = $request->withScene('index')
            ->withRule('page')
            ->validatedData();

        $output = $this->useCase->findUserList($user,$inputData);

        return $output->toViewData();
    }

    /**
     * @throws Throwable
     */
    public function store(UserRequest $request): string
    {
        $this->useCase->createUser($request->validated());

        return '账户创建成功';
    }

    /**
     * 用户线索账户分配数据
     * @param int $id
     * @return array
     */
    public function accounts(int $id): array
    {
        return $this->useCase->findUserAccounts($id);
    }

    /**
     * 保存用户线索账户分配
     * @param int $id
     * @param Request $request
     * @return string
     */
    public function syncAccounts(int $id, Request $request): string
    {
        $inputData = $request->validate([
            'accountIds' => 'array',
            'accountIds.*' => 'integer',
        ]);

        $this->useCase->syncUserAccounts($id, $inputData['accountIds'] ?? []);

        return '分配成功';
    }
}
