<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;

final class ItemConditionRemoved extends CartEvent
{
    public function __construct(
        string $cartId,
        public readonly CartItem $item,
        public readonly string $conditionName,
    ) {
        parent::__construct($cartId);
    }
}
