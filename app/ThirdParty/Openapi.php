<?php
declare(strict_types=1);

namespace App\ThirdParty;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Illuminate\Support\Facades\Http;

class Openapi
{

    protected readonly string $apiPath;

    protected ?string $apiSecret = null;

    protected ?int $userid = null;

    protected readonly string $serverUrl;

    public function __construct(string $apiPath, string $serverUrl)
    {
        $this->apiPath = $apiPath;
        $this->serverUrl = $serverUrl;
    }

    public function setApiSecret(string $apiSecret): self
    {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    public function setUserid(int $userid): self
    {
        $this->userid = $userid;
        return $this;
    }

    public function request(string $startDate, string $endDate, int $pn = 1, int $ps = 500): array
    {
        if ($this->userid === null || $this->apiSecret === null) {
            throw new RuntimeException('openapi userid 和 apiSecret 未设置');
        }

        $pn = max(1, $pn);
        $ps = min(500, max(1, $ps));

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $this->authorization()
            ])
                ->retry(3, 1000)
                ->timeout(10) // 秒
                ->post($this->serverUrl.$this->apiPath, [
                    'dataType' => '0',
                    'status' => 4,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'pn' => $pn,
                    'ps' => $ps,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('openapi 初始化链接失败: ' . $e->getMessage());
        }

        try {
            $payload = $response->throw()->json();
        } catch (RequestException $e) {
            throw new RuntimeException('openapi 请求失败: ' . $e->getMessage(), previous: $e);
        }

        if (!is_array($payload)) {
            throw new RuntimeException('openapi 返回数据不是有效 JSON 对象');
        }

        return $payload;
    }

    protected function authorization(): string
    {
        return 'Bearer ' . base64_encode($this->userid . ':' . $this->apiSecret);
    }
}
