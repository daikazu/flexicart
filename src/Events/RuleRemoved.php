<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\Conditions\Rules\RuleInterface;

final class RuleRemoved extends CartEvent
{
    public function __construct(
        string $cartId,
        public readonly RuleInterface $rule,
    ) {
        parent::__construct($cartId);
    }
}
