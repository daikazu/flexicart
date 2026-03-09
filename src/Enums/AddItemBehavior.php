<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Enums;

enum AddItemBehavior: string
{
    case Update = 'update';
    case New = 'new';
}
