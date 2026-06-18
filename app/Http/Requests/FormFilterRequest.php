<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FormFilterType;
use Elephant\Validation\Contacts\Validation\Scene;
use Elephant\Validation\Validation\SceneTrait;
use Elephant\Validation\Validation\Validator;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class FormFilterRequest extends Validator implements Scene
{
    use SceneTrait;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(FormFilterType::class)],
            'value' => 'required|string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => '过滤类型',
            'value' => '过滤内容',
        ];
    }

    public function scenes(): array
    {
        return [
            'index' => ['perPage', 'page'],
        ];
    }

    public function pageRules(): array
    {
        return [
            'perPage' => 'required|integer|min:10',
            'page' => 'required|integer|min:1',
            'type' => ['nullable', new Enum(FormFilterType::class)],
        ];
    }
}
