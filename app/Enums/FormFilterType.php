<?php

declare(strict_types=1);

namespace App\Enums;

use Elephant\Enums\Attributes\Description;
use Elephant\Enums\Contacts\Enumerable;
use Elephant\Enums\Contacts\Presenter;
use Elephant\Enums\Traits\GetsAttributes;
use Elephant\Enums\Traits\HasMethods;

enum FormFilterType: string implements Enumerable, Presenter
{
    use HasMethods, GetsAttributes;

    #[Description('姓名')]
    case NAME = 'name';

    #[Description('手机号')]
    case PHONE = 'phone';
}
