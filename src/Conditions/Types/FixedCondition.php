<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Types;

use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Price;

final class FixedCondition extends Condition
{
    public ConditionType $type = ConditionType::FIXED;

    public function calculate(?Price $price = null): Price
    {
        return Price::from($this->value);
    }

    public function formattedValue(): string
    {
        return Price::from($this->value)->formatted();
    }
}
