<?php

declare(strict_types=1);

namespace App\Enums;

use Elephant\Enums\Attributes\Description;
use Elephant\Enums\Contacts\Presenter;
use Elephant\Enums\Contacts\Enumerable;
use Elephant\Enums\Traits\GetsAttributes;
use Elephant\Enums\Traits\HasMethods;

enum Role: string implements Enumerable, Presenter
{
    use HasMethods, GetsAttributes;

    #[Description('管理员')]
    case ADMIN = 'admin';

    #[Description('只读')]
    case VIEWER = 'viewer';

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function abilities(): array
    {
        if ($this->isAdmin()) {
            return ['*'];
        }

        return [self::VIEWER->value];
    }
}
