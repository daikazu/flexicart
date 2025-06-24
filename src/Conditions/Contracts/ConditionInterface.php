<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Contracts;

use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;

interface ConditionInterface
{
    /**
     * Calculate the resulting price based on the current condition type and value.
     *
     * @param  Price|null  $price  The base price to use for percentage calculations.
     *                             This can be null unless the type requires it.
     * @return Price The calculated price object.
     *
     * @throws PriceException If the price is null and required, or other calculation errors occur.
     */
    public function calculate(?Price $price = null): Price;

    /**
     * Format the condition's value for display based on its type.
     */
    public function formattedValue(): string;
}
