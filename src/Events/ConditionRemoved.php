<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;

final class ConditionRemoved extends CartEvent
{
    public function __construct(
        string $cartId,
        public readonly ConditionInterface $condition,
    ) {
        parent::__construct($cartId);
    }
}
