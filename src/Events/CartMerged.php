<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\CartItem;
use Illuminate\Support\Collection;

final class CartMerged extends CartEvent
{
    /**
     * @param  string  $cartId  The target cart ID
     * @param  string  $sourceCartId  The source cart ID that was merged
     * @param  Collection<string, CartItem>  $mergedItems  Items that were merged/added
     * @param  string  $strategy  The merge strategy used
     */
    public function __construct(
        string $cartId,
        public readonly string $sourceCartId,
        public readonly Collection $mergedItems,
        public readonly string $strategy,
    ) {
        parent::__construct($cartId);
    }
}
