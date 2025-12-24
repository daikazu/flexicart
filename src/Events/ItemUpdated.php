<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;

final class ItemUpdated extends CartEvent
{
    /**
     * @param  array<string, mixed>  $changes  The attributes that were changed
     */
    public function __construct(
        string $cartId,
        public readonly CartItem $item,
        public readonly array $changes,
    ) {
        parent::__construct($cartId);
    }
}
