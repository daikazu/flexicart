<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Enums;

enum ConditionType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
}
