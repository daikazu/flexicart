<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Events\CartMerged;
use Daikazu\Flexicart\Strategies\KeepTargetMergeStrategy;
use Daikazu\Flexicart\Strategies\MaxMergeStrategy;
use Daikazu\Flexicart\Strategies\MergeStrategyFactory;
use Daikazu\Flexicart\Strategies\ReplaceMergeStrategy;
use Daikazu\Flexicart\Strategies\SumMergeStrategy;
use Daikazu\Flexicart\Tests\MockStorage;
use Illuminate\Support\Facades\Event;

describe('Cart Merging', function (): void {
    beforeEach(function (): void {
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
        config(['flexicart.events.enabled' => true]);
        config(['flexicart.merge.default_strategy' => 'sum']);
        config(['flexicart.merge.delete_source' => false]); // Don't delete source for testing

        Event::fake();
    });

    describe('Sum Strategy (Default)', function (): void {
        test('sums quantities when merging same item', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $sourceCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget Updated',
                'price'    => 12.00,
                'quantity' => 3,
            ]);

            $targetCart->mergeFrom($sourceCart, 'sum');

            $item = $targetCart->item('product1');
            expect($item)->not->toBeNull();
            expect($item->quantity)->toBe(5); // 2 + 3
            expect($item->name)->toBe('Widget Updated'); // Source name wins
            expect($item->unitPrice()->toFloat())->toBe(12.00); // Source price wins
        });

        test('adds new items from source', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'    => 'product1',
                'name'  => 'Widget',
                'price' => 10.00,
            ]);

            $sourceCart->addItem([
                'id'    => 'product2',
                'name'  => 'Gadget',
                'price' => 20.00,
            ]);

            $targetCart->mergeFrom($sourceCart, 'sum');

            expect($targetCart->items()->count())->toBe(2);
            expect($targetCart->item('product1'))->not->toBeNull();
            expect($targetCart->item('product2'))->not->toBeNull();
            expect($targetCart->item('product2')->name)->toBe('Gadget');
        });

        test('combines conditions from both carts', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addCondition(new FixedCondition('Target Discount', -5.00));
            $sourceCart->addCondition(new FixedCondition('Source Discount', -10.00));

            $targetCart->mergeFrom($sourceCart, 'sum');

            expect($targetCart->conditions()->count())->toBe(2);
        });

        test('source condition replaces target when same name', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addCondition(new FixedCondition('Discount', -5.00));
            $sourceCart->addCondition(new FixedCondition('Discount', -15.00));

            $targetCart->mergeFrom($sourceCart, 'sum');

            expect($targetCart->conditions()->count())->toBe(1);
            $condition = $targetCart->conditions()->first();
            expect($condition->value)->toBe(-15.00);
        });
    });

    describe('Replace Strategy', function (): void {
        test('replaces target item with source item completely', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 5,
            ]);

            $sourceCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget New',
                'price'    => 15.00,
                'quantity' => 2,
            ]);

            $targetCart->mergeFrom($sourceCart, 'replace');

            $item = $targetCart->item('product1');
            expect($item->quantity)->toBe(2); // Source quantity replaces
            expect($item->name)->toBe('Widget New');
            expect($item->unitPrice()->toFloat())->toBe(15.00);
        });

        test('source conditions replace target conditions completely', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addCondition(new FixedCondition('Target Only', -5.00));
            $sourceCart->addCondition(new PercentageCondition('Source Only', -10));

            $targetCart->mergeFrom($sourceCart, 'replace');

            expect($targetCart->conditions()->count())->toBe(1);
            $condition = $targetCart->conditions()->first();
            expect($condition->name)->toBe('Source Only');
        });
    });

    describe('Max Strategy', function (): void {
        test('keeps highest quantity when merging same item', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 5,
            ]);

            $sourceCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget Updated',
                'price'    => 12.00,
                'quantity' => 3,
            ]);

            $targetCart->mergeFrom($sourceCart, 'max');

            $item = $targetCart->item('product1');
            expect($item->quantity)->toBe(5); // Max of 5 and 3
        });

        test('source quantity wins when higher', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $sourceCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget Updated',
                'price'    => 12.00,
                'quantity' => 7,
            ]);

            $targetCart->mergeFrom($sourceCart, 'max');

            $item = $targetCart->item('product1');
            expect($item->quantity)->toBe(7); // Max of 2 and 7
        });
    });

    describe('Keep Target Strategy', function (): void {
        test('keeps target item values when item exists in both', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget Original',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $sourceCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget Updated',
                'price'    => 15.00,
                'quantity' => 5,
            ]);

            $targetCart->mergeFrom($sourceCart, 'keep_target');

            $item = $targetCart->item('product1');
            expect($item->quantity)->toBe(2); // Target kept
            expect($item->name)->toBe('Widget Original'); // Target kept
            expect($item->unitPrice()->toFloat())->toBe(10.00); // Target kept
        });

        test('adds new items from source', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'    => 'product1',
                'name'  => 'Widget',
                'price' => 10.00,
            ]);

            $sourceCart->addItem([
                'id'    => 'product2',
                'name'  => 'New Item',
                'price' => 25.00,
            ]);

            $targetCart->mergeFrom($sourceCart, 'keep_target');

            expect($targetCart->items()->count())->toBe(2);
            expect($targetCart->item('product2'))->not->toBeNull();
        });

        test('keeps target conditions', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addCondition(new FixedCondition('Target Discount', -5.00));
            $sourceCart->addCondition(new PercentageCondition('Source Discount', -10));

            $targetCart->mergeFrom($sourceCart, 'keep_target');

            expect($targetCart->conditions()->count())->toBe(1);
            $condition = $targetCart->conditions()->first();
            expect($condition->name)->toBe('Target Discount');
        });
    });

    describe('Strategy Factory', function (): void {
        test('creates sum strategy', function (): void {
            $strategy = MergeStrategyFactory::make('sum');
            expect($strategy)->toBeInstanceOf(SumMergeStrategy::class);
            expect($strategy->name())->toBe('sum');
        });

        test('creates replace strategy', function (): void {
            $strategy = MergeStrategyFactory::make('replace');
            expect($strategy)->toBeInstanceOf(ReplaceMergeStrategy::class);
            expect($strategy->name())->toBe('replace');
        });

        test('creates max strategy', function (): void {
            $strategy = MergeStrategyFactory::make('max');
            expect($strategy)->toBeInstanceOf(MaxMergeStrategy::class);
            expect($strategy->name())->toBe('max');
        });

        test('creates keep_target strategy', function (): void {
            $strategy = MergeStrategyFactory::make('keep_target');
            expect($strategy)->toBeInstanceOf(KeepTargetMergeStrategy::class);
            expect($strategy->name())->toBe('keep_target');
        });

        test('throws exception for invalid strategy', function (): void {
            MergeStrategyFactory::make('invalid');
        })->throws(InvalidArgumentException::class);

        test('returns available strategies', function (): void {
            $available = MergeStrategyFactory::available();
            expect($available)->toContain('sum', 'replace', 'max', 'keep_target');
        });

        test('uses default strategy from config', function (): void {
            config(['flexicart.merge.default_strategy' => 'max']);
            $strategy = MergeStrategyFactory::default();
            expect($strategy)->toBeInstanceOf(MaxMergeStrategy::class);
        });

        test('can register custom strategy', function (): void {
            MergeStrategyFactory::register('custom', SumMergeStrategy::class);
            expect(MergeStrategyFactory::available())->toContain('custom');
        });
    });

    describe('Merge Events', function (): void {
        test('CartMerged event is dispatched', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $sourceCart->addItem([
                'id'    => 'product1',
                'name'  => 'Widget',
                'price' => 10.00,
            ]);

            $targetCart->mergeFrom($sourceCart, 'sum');

            Event::assertDispatched(CartMerged::class, function (CartMerged $event) use ($targetCart, $sourceCart): bool {
                return $event->cartId === $targetCart->id()
                    && $event->sourceCartId === $sourceCart->id()
                    && $event->strategy === 'sum'
                    && $event->mergedItems->count() === 1;
            });
        });
    });

    describe('Merge with Strategy Object', function (): void {
        test('accepts strategy object instead of string', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $sourceCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 3,
            ]);

            $targetCart->mergeFrom($sourceCart, new MaxMergeStrategy);

            expect($targetCart->item('product1')->quantity)->toBe(3);
        });
    });

    describe('Merge Edge Cases', function (): void {
        test('merging into itself does nothing', function (): void {
            $storage = new MockStorage('cart');
            $cart = new Cart($storage);

            $cart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $cart->mergeFrom($cart);

            expect($cart->item('product1')->quantity)->toBe(2); // Unchanged
            Event::assertNotDispatched(CartMerged::class);
        });

        test('merging empty source cart works', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'    => 'product1',
                'name'  => 'Widget',
                'price' => 10.00,
            ]);

            $targetCart->mergeFrom($sourceCart);

            expect($targetCart->items()->count())->toBe(1);
            Event::assertDispatched(CartMerged::class);
        });

        test('merging into empty target cart works', function (): void {
            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $sourceCart->addItem([
                'id'    => 'product1',
                'name'  => 'Widget',
                'price' => 10.00,
            ]);

            $targetCart->mergeFrom($sourceCart);

            expect($targetCart->items()->count())->toBe(1);
            expect($targetCart->item('product1'))->not->toBeNull();
        });

        test('uses default strategy when none specified', function (): void {
            config(['flexicart.merge.default_strategy' => 'sum']);

            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $targetCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $sourceCart->addItem([
                'id'       => 'product1',
                'name'     => 'Widget',
                'price'    => 10.00,
                'quantity' => 3,
            ]);

            $targetCart->mergeFrom($sourceCart);

            expect($targetCart->item('product1')->quantity)->toBe(5); // Sum strategy
        });
    });

    describe('Source Cart Deletion', function (): void {
        test('deletes source cart when configured', function (): void {
            config(['flexicart.merge.delete_source' => true]);

            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $sourceCart->addItem([
                'id'    => 'product1',
                'name'  => 'Widget',
                'price' => 10.00,
            ]);

            $sourceCart->addCondition(new FixedCondition('Discount', -5.00));

            $targetCart->mergeFrom($sourceCart);

            expect($sourceCart->isEmpty())->toBeTrue();
            expect($sourceCart->conditions()->isEmpty())->toBeTrue();
        });

        test('keeps source cart when not configured to delete', function (): void {
            config(['flexicart.merge.delete_source' => false]);

            $targetStorage = new MockStorage('target-cart');
            $sourceStorage = new MockStorage('source-cart');

            $targetCart = new Cart($targetStorage);
            $sourceCart = new Cart($sourceStorage);

            $sourceCart->addItem([
                'id'    => 'product1',
                'name'  => 'Widget',
                'price' => 10.00,
            ]);

            $targetCart->mergeFrom($sourceCart);

            expect($sourceCart->isEmpty())->toBeFalse();
        });
    });
});
