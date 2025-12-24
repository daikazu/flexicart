<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;

final class ItemAdded extends CartEvent
{
    public function __construct(
        string $cartId,
        public readonly CartItem $item,
    ) {
        parent::__construct($cartId);
    }
}
