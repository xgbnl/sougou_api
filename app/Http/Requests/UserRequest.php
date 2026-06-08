<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Elephant\Validation\Contacts\Validation\Scene;
use Elephant\Validation\Validation\SceneTrait;
use Elephant\Validation\Validation\Validator;
use Illuminate\Contracts\Validation\Rule;

final class UserRequest extends Validator implements Scene
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
            'display_name' => 'required',
            'username' => 'required|string|alpha',
            'password' => 'required|string|regex:/^[A-Za-z0-9_\-\+]+$/',
        ];
    }

    public function attributes(): array
    {
        return [
            'display_name' => '账号名称',
            'username' => '账号',
            'password' => '密码',
        ];
    }

    /**
     * Configuring custom validation scenarios.
     */
    public function scenes(): array
    {
        return [
            'index' => ['perPage','page'],
        ];
    }

    public function pageRules(): array
    {
        return [
            'perPage' => 'required|integer|min:10',
            'page' => 'required|integer|min:1',
        ];
    }
}
