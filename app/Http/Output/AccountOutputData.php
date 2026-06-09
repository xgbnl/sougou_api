<?php

declare(strict_types=1);

namespace App\Http\Output;

use App\Models\Account;
use App\UseCases\Contracts\OutputData;
use Illuminate\Database\Eloquent\Model;

readonly final class AccountOutputData implements OutputData
{

    public function transform(Model|Account $model): array
    {
        return [
            'eId' => $model->e_id,
            'status' => $model->status->toViewModel(),
            'secret' => $this->maskKeepLast($model->secret),
        ];
    }

    /**
     * 脱敏：只展示字符串的后 N 位，前面用指定字符替换（支持 UTF-8 多字节）
     *
     * @param string $input 待处理字符串
     * @param int $visible 后面保留的字符数（N），若大于或等于长度则返回原字符串
     * @param string $maskChar 脱敏用字符，默认 '*'
     * @return string 脱敏后的字符串
     */
    protected function maskKeepLast(string $input, int $visible = 4, string $maskChar = '*'): string
    {
        if ($visible < 0) {
            $visible = 0;
        }
        $len = mb_strlen($input, 'UTF-8');
        if ($visible >= $len) {
            return $input;
        }
        $prefixLen = $len - $visible;
        // 构造掩码（重复 maskChar，按字符计数）
        $mask = str_repeat($maskChar, $prefixLen);
        $tail = mb_substr($input, -$visible, $visible, 'UTF-8');
        return $mask . $tail;
    }
}
