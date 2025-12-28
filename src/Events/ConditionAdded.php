<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;

final class ConditionAdded extends CartEvent
{
    public function __construct(
        string $cartId,
        public readonly ConditionInterface $condition,
        public readonly bool $replaced = false,
    ) {
        parent::__construct($cartId);
    }
}
