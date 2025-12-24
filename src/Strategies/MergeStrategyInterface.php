<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Strategies;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Illuminate\Support\Collection;

interface MergeStrategyInterface
{
    /**
     * Merge a source item into a target item.
     *
     * @return array<string, mixed> The merged item data
     */
    public function mergeItem(CartItem $targetItem, CartItem $sourceItem): array;

    /**
     * Handle a new item from source that doesn't exist in target.
     *
     * @return array<string, mixed> The item data to add
     */
    public function handleNewItem(CartItem $sourceItem): array;

    /**
     * Merge source conditions into target conditions.
     *
     * @param  Collection<string, ConditionInterface>  $targetConditions
     * @param  Collection<string, ConditionInterface>  $sourceConditions
     * @return Collection<string, ConditionInterface>
     */
    public function mergeConditions(Collection $targetConditions, Collection $sourceConditions): Collection;

    /**
     * Get the strategy name.
     */
    public function name(): string;
}
