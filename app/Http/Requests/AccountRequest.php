<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Toggle;
use Elephant\Validation\Contacts\Validation\Scene;
use Elephant\Validation\Validation\SceneTrait;
use Elephant\Validation\Validation\Validator;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class AccountRequest extends Validator implements Scene
{
    use SceneTrait;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'eId' => 'required|string|max:10',
            'userid' => 'required|integer',
            'secret' => 'required|string|max:16',
            'status' => ['required', new Enum(Toggle::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => '账号',
            'eId' => '点睛ID',
            'userid' => 'UserId',
            'secret' => '密钥',
            'status' => '状态',
        ];
    }

    public function aliases(): array
    {
        return [
            'eId' => 'e_id',
        ];
    }

    /**
     * Configuring custom validation scenarios.
     */
    public function scenes(): array
    {
        return [
            'index' => ['perPage', 'page'],
            'editStatus' => 'status',
        ];
    }

    public function pageRules(): array
    {
        return [
            'perPage' => 'required|integer|min:10',
            'page' => 'required|integer|min:1',
            'status' => ['nullable', new Enum(Toggle::class)],
        ];
    }
}
