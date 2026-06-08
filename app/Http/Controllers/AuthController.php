<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\UseCases\Interactor\UserInteractor;
use Illuminate\Container\Attributes\CurrentUser;

readonly final class AuthController
{
    /**
     * 授权登录
     * @param AuthRequest $request
     * @param UserInteractor $useCase
     * @return array
     */
    public function login(AuthRequest $request, UserInteractor $useCase): array
    {
        return $useCase->auth($request->validated());
    }

    /**
     * 注销登录
     * @param User $user
     * @return string
     */
    public function logout(#[CurrentUser] User $user): string
    {
        $user->forgetToken();

        return '注销成功';
    }
}
