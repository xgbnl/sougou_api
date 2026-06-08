<?php

declare(strict_types=1);

namespace App\Enums;

use Elephant\Enums\Attributes\Description;
use Elephant\Enums\Contacts\Presenter;
use Elephant\Enums\Contacts\Enumerable;
use Elephant\Enums\Traits\GetsAttributes;
use Elephant\Enums\Traits\HasMethods;

enum Toggle: int implements Enumerable, Presenter
{
    use HasMethods, GetsAttributes;

    #[Description('禁用')]
    case DISABLED = 0;

    #[Description('启用')]
    case ENABLED = 1;
}
