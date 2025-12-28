<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Strategies;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Illuminate\Support\Collection;

/**
 * KeepTarget strategy: Keep target values, only add items that don't exist.
 */
final class KeepTargetMergeStrategy extends AbstractMergeStrategy
{
    public function name(): string
    {
        return 'keep_target';
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeItem(CartItem $targetItem, CartItem $sourceItem): array
    {
        // Target values are kept completely - no merging of duplicate items
        return $this->itemToArray($targetItem);
    }

    /**
     * @param  Collection<string, ConditionInterface>  $targetConditions
     * @param  Collection<string, ConditionInterface>  $sourceConditions
     * @return Collection<string, ConditionInterface>
     */
    public function mergeConditions(Collection $targetConditions, Collection $sourceConditions): Collection
    {
        // Target conditions are kept, source conditions are ignored
        return $targetConditions;
    }
}
