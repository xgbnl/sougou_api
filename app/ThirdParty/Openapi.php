<?php

namespace App\ThirdParty;

use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Support\Facades\Http;

class Openapi
{

    protected readonly string $apiKey;

    protected ?string $apiSecret = null;

    protected readonly string $serverUrl;

    public function __construct(string $apiKey, string $serverUrl)
    {
        $this->apiKey = $apiKey;
        $this->serverUrl = $serverUrl;
    }

    public function setApiSecret(string $apiSecret): self
    {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    /**
     * 平台登录
     * @param string $username
     * @param string $password
     * @return array
     */
    public function login(string $username, string $password): array
    {

    }

    protected function client(string $path, array $body, ?string $accessToken = null): array
    {
        $headers = [
            'apiKey' => $this->apiKey,
        ];

        if (!empty($accessToken)) {
            $headers['accessToken'] = $accessToken;
        }

        try {
            $response = Http::asForm()
                ->withoutVerifying()
                ->withHeaders($headers)
                ->timeout(10) // 秒
                ->post('https://api.e.360.cn' . $path, $body);
        } catch (ConnectionException $e) {
            throw new RuntimeException('openapi 初始化链接失败: ' . $e->getMessage());
        }


    }

    /**
     * 按官方流程加密密码：md5 -> AES-128-CBC -> hex(lower)
     *
     * @param string $password 原始密码
     * @param string $apiSecret 至少 32 字节（前16为 key，后16 为 iv）
     * @return string 64 字符的小写 hex 密文
     * @throws InvalidArgumentException
     */
    protected function encryptPassword(string $password, string $apiSecret): string
    {
        if (strlen($apiSecret) < 32) {
            throw new InvalidArgumentException('apiSecret must be at least 32 bytes.');
        }

        $md5 = md5($password); // 32 字符小写 hex

        $key = substr($apiSecret, 0, 16);
        $iv = substr($apiSecret, 16, 16);

        // OPENSSL_RAW_DATA 返回原始二进制密文，使用默认 PKCS#7 填充（与 AES-CBC 兼容）
        $cipherRaw = openssl_encrypt($md5, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipherRaw === false) {
            throw new RuntimeException('OpenSSL encryption failed.');
        }

        // 64 字符（32 字节密文 -> 64 hex）
        return strtolower(bin2hex($cipherRaw));
    }

    /**
     * 可选：解密（用于测试/验证），输入为 64 字符 hex
     *
     * @param string $hexCipher 64 字符 hex
     * @param string $apiSecret 至少 32 字节
     * @return string 明文 MD5（32 字符小写 hex）
     * @throws InvalidArgumentException|RuntimeException
     */
    protected function decryptPassword(string $hexCipher, string $apiSecret): string
    {
        if (strlen($apiSecret) < 32) {
            throw new InvalidArgumentException('apiSecret must be at least 32 bytes.');
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $hexCipher)) {
            throw new InvalidArgumentException('Cipher must be 64-character lowercase hex.');
        }

        $key = substr($apiSecret, 0, 16);
        $iv = substr($apiSecret, 16, 16);

        $cipherRaw = hex2bin($hexCipher);
        if ($cipherRaw === false) {
            throw new RuntimeException('hex2bin failed.');
        }

        $plain = openssl_decrypt($cipherRaw, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('OpenSSL decryption failed.');
        }

        // 返回 MD5（32 字符小写 hex）
        return $plain;
    }
}
