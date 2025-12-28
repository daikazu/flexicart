<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;

final class ItemQuantityUpdated extends CartEvent
{
    public function __construct(
        string $cartId,
        public readonly CartItem $item,
        public readonly int $oldQuantity,
        public readonly int $newQuantity,
    ) {
        parent::__construct($cartId);
    }
}
