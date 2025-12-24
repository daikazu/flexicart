<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Contracts;

use Brick\Math\Exception\MathException;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;

interface CartItemInterface
{
    /**
     * Add a condition to the item.
     */
    public function addCondition(Condition $condition): self;

    /**
     * Remove a condition from the item by name.
     */
    public function removeCondition(string $conditionName): self;

    /**
     * Clear all conditions from the item.
     */
    public function clearConditions(): self;

    /**
     * Get the item unit price.
     */
    public function unitPrice(): Price;

    /**
     * Calculate the subtotal (price with conditions × quantity).
     *
     * @throws MathException
     * @throws PriceException
     * @throws MoneyMismatchException
     * @throws UnknownCurrencyException
     */
    public function subtotal(): Price;

    /**
     * Calculate and retrieve the unadjusted subtotal based on price and quantity.
     *
     * @throws MathException
     * @throws PriceException
     */
    public function unadjustedSubtotal(): Price;

    /**
     * Convert the item to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
