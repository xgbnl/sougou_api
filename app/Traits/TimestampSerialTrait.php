<?php

namespace App\Traits;

use DateTimeInterface;

trait TimestampSerialTrait
{
    public function updatedAt():string
    {
        return $this->timestampSerialize('updated_at');
    }

    public function createdAt(): string
    {
        return $this->timestampSerialize('created_at');
    }

    protected function timestampSerialize(string $attribute, string $format = 'Y-m-d H:i:s'): string
    {
        if ($this->hasAttribute($attribute) && !empty($this->getAttribute($attribute))) {
            $value = $this->getAttribute($attribute);

            return $value instanceof DateTimeInterface
                ? $value->format($format)
                : $value;
        }

        return '';
    }
}
