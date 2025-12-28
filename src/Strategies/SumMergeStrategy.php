<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Strategies;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Illuminate\Support\Collection;

/**
 * Sum strategy: Add quantities together, source attributes win, combine conditions.
 */
final class SumMergeStrategy extends AbstractMergeStrategy
{
    public function name(): string
    {
        return 'sum';
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeItem(CartItem $targetItem, CartItem $sourceItem): array
    {
        return [
            'id' => $targetItem->id,
            'name' => $sourceItem->name,
            'price' => $sourceItem->unitPrice(),
            'quantity' => $targetItem->quantity + $sourceItem->quantity,
            'taxable' => $sourceItem->taxable,
            'attributes' => $sourceItem->attributes->toArray(),
            'conditions' => $this->mergeItemConditions($targetItem->conditions, $sourceItem->conditions),
        ];
    }

    /**
     * @param  Collection<string, ConditionInterface>  $targetConditions
     * @param  Collection<string, ConditionInterface>  $sourceConditions
     * @return Collection<string, ConditionInterface>
     */
    public function mergeConditions(Collection $targetConditions, Collection $sourceConditions): Collection
    {
        /** @var Collection<string, ConditionInterface> $merged */
        $merged = collect();

        $targetConditions->each(function (ConditionInterface $condition) use ($merged): void {
            $merged->put($this->getConditionName($condition), $condition);
        });

        $sourceConditions->each(function (ConditionInterface $condition) use ($merged): void {
            $merged->put($this->getConditionName($condition), $condition);
        });

        return $merged;
    }
}
