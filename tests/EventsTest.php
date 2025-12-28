<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Events\CartCleared;
use Daikazu\Flexicart\Events\CartReset;
use Daikazu\Flexicart\Events\ConditionAdded;
use Daikazu\Flexicart\Events\ConditionRemoved;
use Daikazu\Flexicart\Events\ConditionsCleared;
use Daikazu\Flexicart\Events\ItemAdded;
use Daikazu\Flexicart\Events\ItemConditionAdded;
use Daikazu\Flexicart\Events\ItemConditionRemoved;
use Daikazu\Flexicart\Events\ItemQuantityUpdated;
use Daikazu\Flexicart\Events\ItemRemoved;
use Daikazu\Flexicart\Events\ItemUpdated;
use Daikazu\Flexicart\Tests\MockStorage;
use Illuminate\Support\Facades\Event;

describe('Cart Events', function (): void {
    beforeEach(function (): void {
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
        config(['flexicart.events.enabled' => true]);

        $this->mockStorage = new MockStorage;
        $this->cart = new Cart($this->mockStorage);

        Event::fake();
    });

    describe('Item Events', function (): void {
        test('ItemAdded event is dispatched when adding a new item', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            Event::assertDispatched(ItemAdded::class, function (ItemAdded $event): bool {
                return $event->item->id === 'product1'
                    && $event->item->name === 'Test Product'
                    && $event->cartId === $this->cart->id();
            });
        });

        test('ItemAdded event is dispatched when adding a CartItem object', function (): void {
            $cartItem = new CartItem([
                'id' => 'product2',
                'name' => 'CartItem Product',
                'price' => 50.00,
            ]);

            $this->cart->addItem($cartItem);

            Event::assertDispatched(ItemAdded::class, function (ItemAdded $event): bool {
                return $event->item->id === 'product2'
                    && $event->item->name === 'CartItem Product';
            });
        });

        test('ItemQuantityUpdated event is dispatched when adding existing item', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
                'quantity' => 2,
            ]);

            Event::fake(); // Reset fake to only capture the second add

            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
                'quantity' => 3,
            ]);

            Event::assertDispatched(ItemQuantityUpdated::class, function (ItemQuantityUpdated $event): bool {
                return $event->item->id === 'product1'
                    && $event->oldQuantity === 2
                    && $event->newQuantity === 5;
            });

            Event::assertNotDispatched(ItemAdded::class);
        });

        test('ItemUpdated event is dispatched when updating an item', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            Event::fake();

            $this->cart->updateItem('product1', [
                'quantity' => 5,
                'name' => 'Updated Product',
            ]);

            Event::assertDispatched(ItemUpdated::class, function (ItemUpdated $event): bool {
                return $event->item->id === 'product1'
                    && $event->item->name === 'Updated Product'
                    && $event->item->quantity === 5
                    && $event->changes === ['quantity' => 5, 'name' => 'Updated Product'];
            });
        });

        test('ItemUpdated event is not dispatched for non-existent item', function (): void {
            $this->cart->updateItem('non-existent', ['quantity' => 5]);

            Event::assertNotDispatched(ItemUpdated::class);
        });

        test('ItemRemoved event is dispatched when removing an item', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            Event::fake();

            $this->cart->removeItem('product1');

            Event::assertDispatched(ItemRemoved::class, function (ItemRemoved $event): bool {
                return $event->item->id === 'product1'
                    && $event->item->name === 'Test Product';
            });
        });

        test('ItemRemoved event is not dispatched for non-existent item', function (): void {
            $this->cart->removeItem('non-existent');

            Event::assertNotDispatched(ItemRemoved::class);
        });
    });

    describe('Cart Clear and Reset Events', function (): void {
        test('CartCleared event is dispatched when clearing items', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Product 1',
                'price' => 100.00,
            ]);

            $this->cart->addItem([
                'id' => 'product2',
                'name' => 'Product 2',
                'price' => 200.00,
            ]);

            Event::fake();

            $this->cart->clear();

            Event::assertDispatched(CartCleared::class, function (CartCleared $event): bool {
                return $event->items->count() === 2
                    && $event->items->has('product1')
                    && $event->items->has('product2');
            });
        });

        test('CartCleared event is not dispatched for empty cart', function (): void {
            $this->cart->clear();

            Event::assertNotDispatched(CartCleared::class);
        });

        test('CartReset event is dispatched when resetting cart', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Product 1',
                'price' => 100.00,
            ]);

            $this->cart->addCondition(new FixedCondition('Discount', -10.00));

            Event::fake();

            $this->cart->reset();

            Event::assertDispatched(CartReset::class, function (CartReset $event): bool {
                return $event->items->count() === 1
                    && $event->conditions->count() === 1;
            });
        });

        test('CartReset event is not dispatched for empty cart', function (): void {
            $this->cart->reset();

            Event::assertNotDispatched(CartReset::class);
        });
    });

    describe('Condition Events', function (): void {
        test('ConditionAdded event is dispatched when adding a condition', function (): void {
            $condition = new FixedCondition('Discount', -10.00);

            $this->cart->addCondition($condition);

            Event::assertDispatched(ConditionAdded::class, function (ConditionAdded $event) use ($condition): bool {
                return $event->condition->name === 'Discount'
                    && $event->condition->value === -10.00
                    && $event->replaced === false;
            });
        });

        test('ConditionAdded event indicates replacement when condition name exists', function (): void {
            $this->cart->addCondition(new FixedCondition('Discount', -10.00));

            Event::fake();

            $this->cart->addCondition(new FixedCondition('Discount', -20.00));

            Event::assertDispatched(ConditionAdded::class, function (ConditionAdded $event): bool {
                return $event->condition->value === -20.00
                    && $event->replaced === true;
            });
        });

        test('ConditionAdded event works with FixedCondition', function (): void {
            $this->cart->addCondition(new FixedCondition('ArrayDiscount', -15.00));

            Event::assertDispatched(ConditionAdded::class, function (ConditionAdded $event): bool {
                return $event->condition->name === 'ArrayDiscount'
                    && $event->condition->value === -15.00;
            });
        });

        test('ConditionRemoved event is dispatched when removing a condition', function (): void {
            $this->cart->addCondition(new FixedCondition('Discount', -10.00));

            Event::fake();

            $this->cart->removeCondition('Discount');

            Event::assertDispatched(ConditionRemoved::class, function (ConditionRemoved $event): bool {
                return $event->condition->name === 'Discount'
                    && $event->condition->value === -10.00;
            });
        });

        test('ConditionRemoved event is not dispatched for non-existent condition', function (): void {
            $this->cart->removeCondition('NonExistent');

            Event::assertNotDispatched(ConditionRemoved::class);
        });

        test('ConditionsCleared event is dispatched when clearing conditions', function (): void {
            $this->cart->addCondition(new FixedCondition('Discount', -10.00));
            $this->cart->addCondition(new PercentageCondition('Tax', 8.5));

            Event::fake();

            $this->cart->clearConditions();

            Event::assertDispatched(ConditionsCleared::class, function (ConditionsCleared $event): bool {
                return $event->conditions->count() === 2;
            });
        });

        test('ConditionsCleared event is not dispatched when no conditions', function (): void {
            $this->cart->clearConditions();

            Event::assertNotDispatched(ConditionsCleared::class);
        });
    });

    describe('Item Condition Events', function (): void {
        test('ItemConditionAdded event is dispatched when adding item condition', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            $condition = new PercentageCondition('Item Discount', -10, ConditionTarget::ITEM);
            $this->cart->addItemCondition('product1', $condition);

            Event::assertDispatched(ItemConditionAdded::class, function (ItemConditionAdded $event): bool {
                return $event->item->id === 'product1'
                    && $event->condition->name === 'Item Discount'
                    && (int) $event->condition->value === -10;
            });
        });

        test('ItemConditionAdded event is not dispatched for non-existent item', function (): void {
            $condition = new PercentageCondition('Discount', -10, ConditionTarget::ITEM);
            $this->cart->addItemCondition('non-existent', $condition);

            Event::assertNotDispatched(ItemConditionAdded::class);
        });

        test('ItemConditionRemoved event is dispatched when removing item condition', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            $condition = new PercentageCondition('Item Discount', -10, ConditionTarget::ITEM);
            $this->cart->addItemCondition('product1', $condition);

            Event::fake();

            $this->cart->removeItemCondition('product1', 'Item Discount');

            Event::assertDispatched(ItemConditionRemoved::class, function (ItemConditionRemoved $event): bool {
                return $event->item->id === 'product1'
                    && $event->conditionName === 'Item Discount';
            });
        });

        test('ItemConditionRemoved event is not dispatched for non-existent item', function (): void {
            $this->cart->removeItemCondition('non-existent', 'SomeCondition');

            Event::assertNotDispatched(ItemConditionRemoved::class);
        });
    });

    describe('Events Configuration', function (): void {
        test('events are not dispatched when disabled in config', function (): void {
            config(['flexicart.events.enabled' => false]);

            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            Event::assertNotDispatched(ItemAdded::class);
        });

        test('events are dispatched by default', function (): void {
            config(['flexicart.events.enabled' => true]);

            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            Event::assertDispatched(ItemAdded::class);
        });
    });

    describe('Event Properties', function (): void {
        test('all events have cartId and occurredAt properties', function (): void {
            $this->cart->addItem([
                'id' => 'product1',
                'name' => 'Test Product',
                'price' => 100.00,
            ]);

            Event::assertDispatched(ItemAdded::class, function (ItemAdded $event): bool {
                return $event->cartId === $this->cart->id()
                    && $event->occurredAt instanceof DateTimeImmutable;
            });
        });
    });
});
