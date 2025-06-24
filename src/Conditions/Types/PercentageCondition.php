<?php

namespace Daikazu\Flexicart\Conditions\Types;

use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;

final class PercentageCondition extends Condition
{
    public ConditionType $type = ConditionType::PERCENTAGE;

    public function calculate(?Price $price = null): Price
    {
        if ($price === null) {
            throw new PriceException('Price is required for percentage conditions.');
        }

        try {
            $multiplier = $this->value / 100;

            $money = $price
                ->toRational()
                ->multipliedBy($multiplier)
                ->to($price->getContext(), RoundingMode::HALF_UP);

            return new Price($money);
        } catch (MathException $e) {
            throw new PriceException($e->getMessage());
        }
    }

    public function formattedValue(): string
    {
        return rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.') . '%';
    }
}
