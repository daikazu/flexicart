<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Strategies;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Illuminate\Support\Collection;

abstract class AbstractMergeStrategy implements MergeStrategyInterface
{
    /**
     * Convert a CartItem to an array for creating a new CartItem.
     *
     * @return array<string, mixed>
     */
    protected function itemToArray(CartItem $item): array
    {
        return [
            'id'         => $item->id,
            'name'       => $item->name,
            'price'      => $item->unitPrice(),
            'quantity'   => $item->quantity,
            'taxable'    => $item->taxable,
            'attributes' => $item->attributes->toArray(),
            'conditions' => $item->conditions,
        ];
    }

    /**
     * Handle a new item from source that doesn't exist in target.
     * Default behavior: add the item as-is.
     *
     * @return array<string, mixed>
     */
    public function handleNewItem(CartItem $sourceItem): array
    {
        return $this->itemToArray($sourceItem);
    }

    /**
     * Get the condition name as a string.
     */
    protected function getConditionName(ConditionInterface $condition): string
    {
        $data = $condition->toArray();

        return isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
    }

    /**
     * Merge item conditions.
     *
     * @param  Collection<int, ConditionInterface>  $targetConditions
     * @param  Collection<int, ConditionInterface>  $sourceConditions
     * @return Collection<int, ConditionInterface>
     */
    protected function mergeItemConditions(Collection $targetConditions, Collection $sourceConditions): Collection
    {
        /** @var Collection<string, ConditionInterface> $merged */
        $merged = collect();

        $targetConditions->each(function (ConditionInterface $condition) use ($merged): void {
            $merged->put($this->getConditionName($condition), $condition);
        });

        $sourceConditions->each(function (ConditionInterface $condition) use ($merged): void {
            $merged->put($this->getConditionName($condition), $condition);
        });

        /** @var Collection<int, ConditionInterface> $result */
        $result = $merged->values();

        return $result;
    }
}
