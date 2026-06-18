<?php

declare(strict_types=1);

namespace App\UseCases\Interactor;

use App\Enums\FormFilterType;
use App\Models\FormFilter;
use App\Models\User;
use App\UseCases\Contracts\LengthAwareOutPut;
use App\UseCases\Contracts\OutPutPort;
use App\UseCases\Exceptions\UseCaseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HigherOrderWhenProxy;

readonly final class FormFilterInteractor
{
    private const int CACHE_SECONDS = 300;

    public function findFormFilterList(User $user, array $inputData): OutPutPort
    {
        $this->ensureAdmin($user);

        $pages = FormFilter::query()
            ->select(['id', 'type', 'value', 'created_at'])
            ->when(isset($inputData['type']), function (Builder|HigherOrderWhenProxy $query) use ($inputData): Builder|HigherOrderWhenProxy {
                return $query->where('type', $inputData['type']);
            })
            ->orderByDesc('id')
            ->paginate(perPage: $inputData['perPage'], page: $inputData['page']);

        return LengthAwareOutPut::pages($pages);
    }

    public function createFormFilter(User $user, array $inputData): void
    {
        $this->ensureAdmin($user);

        $type = FormFilterType::from($inputData['type']);
        $value = trim((string)$inputData['value']);

        if ($value === '') {
            throw new UseCaseException('过滤内容不能为空');
        }

        $exists = FormFilter::query()
            ->where('type', $type->value)
            ->where('value', $value)
            ->exists();

        if ($exists) {
            throw new UseCaseException('过滤项已存在');
        }

        FormFilter::query()->create([
            'type' => $type,
            'value' => $value,
        ]);

        $this->clearCache($type);
    }

    public function deleteFormFilter(User $user, int $id): void
    {
        $this->ensureAdmin($user);

        $filter = FormFilter::query()->find($id);
        if (empty($filter)) {
            throw new ModelNotFoundException('过滤项不存在');
        }

        $type = $filter->type;

        if (!$filter->delete()) {
            throw new UseCaseException('删除失败，请联系管理员');
        }

        $this->clearCache($type);
    }

    public function shouldSkipName(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        foreach ($this->values(FormFilterType::NAME) as $filterName) {
            if ($filterName !== '' && str_contains($name, $filterName)) {
                return true;
            }
        }

        return false;
    }

    public function shouldSkipPhone(string $phone): bool
    {
        $phone = trim($phone);

        if ($phone === '') {
            return false;
        }

        foreach ($this->values(FormFilterType::PHONE) as $filterPhone) {
            if ($phone === $filterPhone) {
                return true;
            }
        }

        return false;
    }

    private function values(FormFilterType $type): array
    {
        return Cache::remember($this->cacheKey($type), self::CACHE_SECONDS, function () use ($type): array {
            return FormFilter::query()
                ->where('type', $type->value)
                ->pluck('value')
                ->map(fn($value): string => trim((string)$value))
                ->filter()
                ->values()
                ->all();
        });
    }

    private function clearCache(FormFilterType $type): void
    {
        Cache::forget($this->cacheKey($type));
    }

    private function cacheKey(FormFilterType $type): string
    {
        return 'form-filters:' . $type->value;
    }

    private function ensureAdmin(User $user): void
    {
        if (!$user->role->isAdmin()) {
            throw new UseCaseException('无操作权限');
        }
    }
}
