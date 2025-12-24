<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Types;

use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;

final class PercentageTaxCondition extends Condition
{
    public ConditionType $type = ConditionType::PERCENTAGE;

    public ConditionTarget $target = ConditionTarget::TAXABLE;

    public function calculate(?Price $price = null): Price
    {
        if (! $price instanceof \Daikazu\Flexicart\Price) {
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
