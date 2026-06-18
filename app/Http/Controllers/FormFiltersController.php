<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Output\FormFilterOutputData;
use App\Http\Requests\FormFilterRequest;
use App\Models\User;
use App\UseCases\Interactor\FormFilterInteractor;
use Illuminate\Container\Attributes\CurrentUser;

readonly final class FormFiltersController
{
    public function __construct(protected FormFilterInteractor $useCase)
    {
    }

    public function index(FormFilterRequest $request, #[CurrentUser] User $user): array
    {
        $inputData = $request->withScene('index')
            ->withRule('page')
            ->validatedData();

        $output = $this->useCase->findFormFilterList($user, $inputData);

        return $output->toViewData(new FormFilterOutputData());
    }

    public function store(FormFilterRequest $request, #[CurrentUser] User $user): string
    {
        $this->useCase->createFormFilter($user, $request->validatedData());

        return '过滤项添加成功';
    }

    public function destroy(int $id, #[CurrentUser] User $user): string
    {
        $this->useCase->deleteFormFilter($user, $id);

        return '删除成功';
    }
}
