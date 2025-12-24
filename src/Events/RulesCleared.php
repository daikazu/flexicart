<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use Daikazu\Flexicart\Conditions\Rules\RuleInterface;
use Illuminate\Support\Collection;

final class RulesCleared extends CartEvent
{
    /**
     * @param  Collection<array-key, RuleInterface>  $rules  The rules that were cleared
     */
    public function __construct(
        string $cartId,
        public readonly Collection $rules,
    ) {
        parent::__construct($cartId);
    }
}
