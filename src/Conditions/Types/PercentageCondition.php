<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Types;

use Brick\Math\Exception\MathException;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;

final class PercentageCondition extends Condition
{
    public ConditionType $type = ConditionType::PERCENTAGE;

    public function calculate(?Price $price = null): Price
    {
        if (! $price instanceof \Daikazu\Flexicart\Price) {
            throw new PriceException('Price is required for percentage conditions.');
        }

        try {
            // Rounds half up to the price's own context.
            return $price->multiplyBy($this->value / 100);
        } catch (MathException $e) {
            throw new PriceException($e->getMessage());
        }
    }

    public function formattedValue(): string
    {
        return rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.') . '%';
    }
}
