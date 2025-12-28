<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Rules;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Collection;

/**
 * Rules are advanced conditions that have access to full cart context.
 * They can make decisions based on items, quantities, and subtotals.
 */
interface RuleInterface extends ConditionInterface
{
    /**
     * Get the rule name.
     */
    public function getName(): string;

    /**
     * Set the cart context for this rule.
     *
     * @param  Collection<string, CartItem>  $items
     */
    public function setCartContext(Collection $items, Price $subtotal): self;

    /**
     * Check if this rule applies to the current cart state.
     */
    public function applies(): bool;

    /**
     * Get the discount amount this rule provides.
     * This is called only if applies() returns true.
     */
    public function getDiscount(): Price;
}
