<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;
use Illuminate\Support\Collection;

final class CartCleared extends CartEvent
{
    /**
     * @param  Collection<array-key, CartItem>  $items  The items that were cleared
     */
    public function __construct(
        string $cartId,
        public readonly Collection $items,
    ) {
        parent::__construct($cartId);
    }
}
