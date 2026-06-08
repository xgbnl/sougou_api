<?php

declare(strict_types=1);

namespace App\Traits;

use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use DateTimeInterface;

trait HasAccessToken
{
    /**
     * 创建新的访问令牌（允许多设备同时登录）
     * @param string $name
     * @param array $abilities
     * @param DateTimeInterface|null $expiresAt
     * @return NewAccessToken
     */
    public function makeToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();

        $data = [
            'tokenable_id' => $this->getKey(),
            'tokenable_type' => self::class,
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ];

        // 每次登录都创建新的token，允许多设备同时登录
        $token = $this->tokens()->create($data);

        return new NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }

    /**
     * 清空令牌
     *
     * @return void
     */
    public function forgetToken(): void
    {
        /**
         * @var PersonalAccessToken $token
         */
        $token = $this->currentAccessToken();

        $token->update(['token' => $this->getKey() . ':' . $this->getAttribute('name') . ':' . 'nullable']);
    }
}
