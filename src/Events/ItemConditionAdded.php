<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;

final class ItemConditionAdded extends CartEvent
{
    public function __construct(
        string $cartId,
        public readonly CartItem $item,
        public readonly ConditionInterface $condition,
    ) {
        parent::__construct($cartId);
    }
}
