<?php

declare(strict_types=1);

namespace App\Enums;

use Elephant\Enums\Attributes\Description;
use Elephant\Enums\Contacts\Presenter;
use Elephant\Enums\Contacts\Enumerable;
use Elephant\Enums\Traits\GetsAttributes;
use Elephant\Enums\Traits\HasMethods;

enum AccountChannel: string implements Enumerable, Presenter
{
    use HasMethods, GetsAttributes;

    #[Description('360')]
    case QI_HU = 'qihu';

    #[Description('百度')]
    case BAIDU = 'baidu';
}
