<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Illuminate\Support\Collection;

final class CartReset extends CartEvent
{
    /**
     * @param  Collection<array-key, CartItem>  $items  The items that were cleared
     * @param  Collection<array-key, ConditionInterface>  $conditions  The conditions that were cleared
     */
    public function __construct(
        string $cartId,
        public readonly Collection $items,
        public readonly Collection $conditions,
    ) {
        parent::__construct($cartId);
    }
}
