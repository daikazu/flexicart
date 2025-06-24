<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Enums;

enum ConditionTarget: string
{
    case ITEM = 'item';
    case SUBTOTAL = 'subtotal';
    case TAXABLE = 'taxable';
}
