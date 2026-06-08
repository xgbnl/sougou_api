<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Output\UserOutputData;
use App\Http\Requests\UserRequest;
use App\UseCases\Interactor\UserInteractor;
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
    public function index(UserRequest $request): array
    {
        $inputData = $request->withScene('index')
            ->withRule('page')
            ->validatedData();

        $output = $this->useCase->findUserList($inputData);

        return $output->toViewData(new UserOutputData());
    }

    /**
     * @throws Throwable
     */
    public function store(UserRequest $request): string
    {
        $this->useCase->createUser($request->validated());

        return '账户创建成功';
    }
}
