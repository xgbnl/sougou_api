<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AccountChannel;
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
            'channel' => ['required', new Enum(AccountChannel::class)],
            'username' => 'required|string',
            'eId' => 'nullable|required_if:channel,' . AccountChannel::QI_HU->value . '|string|max:10',
            'userid' => 'nullable|required_if:channel,' . AccountChannel::QI_HU->value . '|integer',
            'secret' => 'nullable|required_if:channel,' . AccountChannel::QI_HU->value . '|string|max:16',
            'status' => ['required', new Enum(Toggle::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'channel' => '账户渠道',
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
            'channel' => ['nullable', new Enum(AccountChannel::class)],
        ];
    }
}
