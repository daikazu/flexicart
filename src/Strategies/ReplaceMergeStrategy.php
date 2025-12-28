<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Strategies;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Illuminate\Support\Collection;

/**
 * Replace strategy: Source replaces target completely.
 */
final class ReplaceMergeStrategy extends AbstractMergeStrategy
{
    public function name(): string
    {
        return 'replace';
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeItem(CartItem $targetItem, CartItem $sourceItem): array
    {
        // Source completely replaces target
        return $this->itemToArray($sourceItem);
    }

    /**
     * @param  Collection<string, ConditionInterface>  $targetConditions
     * @param  Collection<string, ConditionInterface>  $sourceConditions
     * @return Collection<string, ConditionInterface>
     */
    public function mergeConditions(Collection $targetConditions, Collection $sourceConditions): Collection
    {
        // Source conditions completely replace target conditions
        return $sourceConditions;
    }
}
