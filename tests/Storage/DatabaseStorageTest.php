<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageTaxCondition;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Models\CartItemModel;
use Daikazu\Flexicart\Models\CartModel;
use Daikazu\Flexicart\Price;
use Daikazu\Flexicart\Storage\DatabaseStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    // Mock Auth facade
    Auth::shouldReceive('check')->andReturn(false)->byDefault();
    Auth::shouldReceive('id')->andReturn(null)->byDefault();

    // Set up database configuration
    $this->app['config']->set('database.default', 'testing');
    $this->app['config']->set('database.connections.testing', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);

    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    // Create the cart tables
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

    $this->app['config']->set('flexicart.storage', 'database');
});

describe('DatabaseStorage', function (): void {
    describe('Constructor and Initialization', function (): void {
        test('can be instantiated with CartModel', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            expect($storage)->toBeInstanceOf(DatabaseStorage::class);
        });

        test('initializes cart ID for guest users', function (): void {
            Auth::shouldReceive('check')->andReturn(false);
            Auth::shouldReceive('id')->andReturn(null);

            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartId = $storage->getCartId();
            expect($cartId)->toBeString()
                ->and($cartId)->not()->toBeEmpty();
        });

        test('initializes cart ID for authenticated users', function (): void {
            Auth::shouldReceive('check')->andReturn(true);
            Auth::shouldReceive('id')->andReturn(123);

            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartId = $storage->getCartId();
            expect($cartId)->toBeString()
                ->and($cartId)->not()->toBeEmpty();

            // Verify cart was created with user_id
            $cart = CartModel::find((int) $cartId);
            expect($cart->user_id)->toBe(123);
        });
    });

    describe('Cart ID Methods', function (): void {
        test('getCartId returns string representation of cart ID', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartId = $storage->getCartId();
            expect($cartId)->toBeString()
                ->and(is_numeric($cartId))->toBeTrue();
        });

        test('getCartById returns cart data for existing cart', function (): void {
            // Create a cart with test data
            $cart = CartModel::create(['session_id' => 'test_session']);
            CartItemModel::create([
                'cart_id'    => $cart->id,
                'item_id'    => 'item1',
                'name'       => 'Test Item',
                'price'      => 10.99,
                'quantity'   => 2,
                'attributes' => ['color' => 'red'],
                'conditions' => [['name' => 'discount', 'type' => 'percentage', 'value' => 10]],
            ]);

            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $retrievedCart = $storage->getCartById((string) $cart->id);

            expect($retrievedCart)->not()->toBeNull()
                ->and($retrievedCart)->toHaveKey('items')
                ->and($retrievedCart)->toHaveKey('conditions')
                ->and($retrievedCart['items'])->toHaveKey('item1')
                ->and($retrievedCart['items']['item1']['name'])->toBe('Test Item')
                ->and($retrievedCart['items']['item1']['price'])->toBeInstanceOf(Price::class)
                ->and($retrievedCart['items']['item1']['quantity'])->toBe(2);
        });

        test('getCartById returns null for non-existing cart', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $retrievedCart = $storage->getCartById('999999');

            expect($retrievedCart)->toBeNull();
        });
    });

    describe('Data Retrieval (get method)', function (): void {
        test('get returns empty cart structure when no data exists', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cart = $storage->get();

            expect($cart)->toHaveKey('items')
                ->and($cart)->toHaveKey('conditions')
                ->and($cart['items'])->toBeArray()
                ->and($cart['conditions'])->toBeArray()
                ->and($cart['items'])->toBeEmpty();
        });

        test('get returns cart data with items and conditions', function (): void {
            // Create a cart with test data
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            // Add test data directly to database
            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item1',
                'name'       => 'Test Item 1',
                'price'      => 15.50,
                'quantity'   => 3,
                'attributes' => ['size' => 'large'],
                'conditions' => [['name' => 'tax', 'type' => 'percentage', 'value' => 8.5]],
            ]);

            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item2',
                'name'       => 'Test Item 2',
                'price'      => 25.00,
                'quantity'   => 1,
                'attributes' => ['color' => 'blue'],
                'conditions' => [],
            ]);

            // Add global conditions
            $cart = CartModel::find($cartId);
            $cart->conditions = [['name' => 'shipping', 'type' => 'fixed', 'value' => 5.00]];
            $cart->save();

            $retrievedCart = $storage->get();

            expect($retrievedCart['items'])->toHaveCount(2)
                ->and($retrievedCart['items'])->toHaveKey('item1')
                ->and($retrievedCart['items'])->toHaveKey('item2')
                ->and($retrievedCart['items']['item1']['name'])->toBe('Test Item 1')
                ->and($retrievedCart['items']['item1']['price'])->toBeInstanceOf(Price::class)
                ->and($retrievedCart['items']['item1']['quantity'])->toBe(3)
                ->and($retrievedCart['items']['item1']['attributes'])->toBe(['size' => 'large'])
                ->and($retrievedCart['items']['item1']['conditions'])->toHaveCount(1)
                ->and($retrievedCart['items']['item1']['conditions'][0])->toBeInstanceOf(ConditionInterface::class)
                ->and($retrievedCart['conditions'])->toHaveCount(1)
                ->and($retrievedCart['conditions'][0])->toBeInstanceOf(ConditionInterface::class)
                ->and($retrievedCart['conditions'][0]->name)->toBe('shipping');

        });

        test('get handles Collection conditions correctly', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            // Create cart with Collection conditions
            $cart = CartModel::find($cartId);
            $cart->conditions = collect([['name' => 'discount', 'type' => 'percentage', 'value' => 10]]);
            $cart->save();

            $retrievedCart = $storage->get();

            expect($retrievedCart['conditions'])->toBeArray()
                ->and($retrievedCart['conditions'])->toHaveCount(1)
                ->and($retrievedCart['conditions'][0])->toBeInstanceOf(ConditionInterface::class)
                ->and($retrievedCart['conditions'][0]->name)->toBe('discount');
        });

        test('get handles Condition objects correctly', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            // Create a condition object (mocked since we can't easily create real ones in test)
            $conditionMock = mock(Condition::class);
            $conditionMock->shouldReceive('toArray')->andReturn(['name' => 'test', 'type' => 'fixed', 'value' => 5]);

            // Add item with condition object
            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item1',
                'name'       => 'Test Item',
                'price'      => 10.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [$conditionMock],
            ]);

            $retrievedCart = $storage->get();

            expect($retrievedCart['items']['item1']['conditions'])->toBeArray();
        });
    });

    describe('Data Storage (put method)', function (): void {
        test('put stores cart data with array items', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Test Product',
                        'price'      => 19.99,
                        'quantity'   => 2,
                        'attributes' => ['color' => 'red', 'size' => 'medium'],
                        'conditions' => [['name' => 'discount', 'type' => 'percentage', 'value' => 15]],
                    ],
                ],
                'conditions' => [['name' => 'shipping', 'type' => 'fixed', 'value' => 7.50]],
            ];

            $result = $storage->put($cartData);

            expect($result)->toBe($cartData);

            // Verify data was stored in database
            $cartId = (int) $storage->getCartId();
            $storedItem = CartItemModel::where('cart_id', $cartId)->where('item_id', 'item1')->first();

            expect($storedItem)->not()->toBeNull()
                ->and($storedItem->name)->toBe('Test Product')
                ->and($storedItem->price)->toBe(19.99)
                ->and($storedItem->quantity)->toBe(2)
                ->and($storedItem->getAttribute('attributes'))->toEqual(['color' => 'red', 'size' => 'medium']);

            // Verify global conditions
            $cart = CartModel::find($cartId);
            expect($cart->conditions->toArray())->toHaveCount(1);
        });

        test('put updates existing items correctly', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            // First, create an item
            $initialData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Initial Item',
                        'price'      => 10.00,
                        'quantity'   => 1,
                        'attributes' => ['color' => 'red'],
                        'conditions' => [],
                    ],
                ],
                'conditions' => [],
            ];

            $storage->put($initialData);

            // Verify initial item was stored
            $storedItem = CartItemModel::where('cart_id', $cartId)->where('item_id', 'item1')->first();
            expect($storedItem->name)->toBe('Initial Item')
                ->and($storedItem->quantity)->toBe(1);

            // Now update the item
            $updatedData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Updated Item',
                        'price'      => 25.00,
                        'quantity'   => 3,
                        'attributes' => ['color' => 'blue', 'size' => 'large'],
                        'conditions' => [['name' => 'discount', 'type' => 'percentage', 'value' => 10]],
                    ],
                ],
                'conditions' => [],
            ];

            $result = $storage->put($updatedData);

            expect($result)->toBe($updatedData);

            // Verify item was updated
            $updatedItem = CartItemModel::where('cart_id', $cartId)->where('item_id', 'item1')->first();
            expect($updatedItem->name)->toBe('Updated Item')
                ->and($updatedItem->price)->toBe(25.00)
                ->and($updatedItem->quantity)->toBe(3)
                ->and($updatedItem->getAttribute('attributes'))->toEqual(['color' => 'blue', 'size' => 'large'])
                ->and(CartItemModel::where('cart_id', $cartId)->count())->toBe(1);

            // Verify only one item exists (update, not create)
        });

        test('put handles Collection items and conditions', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartData = [
                'items' => collect([
                    'item1' => [
                        'name'       => 'Collection Item',
                        'price'      => 12.50,
                        'quantity'   => 1,
                        'attributes' => [],
                        'conditions' => [],
                    ],
                ]),
                'conditions' => collect([['name' => 'promo', 'type' => 'fixed', 'value' => 2.00]]),
            ];

            $result = $storage->put($cartData);

            // Should handle collections properly
            $cartId = (int) $storage->getCartId();
            $storedItem = CartItemModel::where('cart_id', $cartId)->first();
            expect($storedItem->name)->toBe('Collection Item');
        });

        test('put removes items no longer in cart', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            // First, add two items
            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item1',
                'name'       => 'Item 1',
                'price'      => 10.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [],
            ]);

            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item2',
                'name'       => 'Item 2',
                'price'      => 15.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [],
            ]);

            expect(CartItemModel::where('cart_id', $cartId)->count())->toBe(2);

            // Now put cart data with only one item
            $cartData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Updated Item 1',
                        'price'      => 12.00,
                        'quantity'   => 2,
                        'attributes' => [],
                        'conditions' => [],
                    ],
                ],
                'conditions' => [],
            ];

            $storage->put($cartData);

            // Should only have one item now
            expect(CartItemModel::where('cart_id', $cartId)->count())->toBe(1);
            $remainingItem = CartItemModel::where('cart_id', $cartId)->first();
            expect($remainingItem->item_id)->toBe('item1')
                ->and($remainingItem->name)->toBe('Updated Item 1');
        });

        test('put handles Price objects correctly', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Price Object Item',
                        'price'      => new Price(29.99),
                        'quantity'   => 1,
                        'attributes' => [],
                        'conditions' => [],
                    ],
                ],
                'conditions' => [],
            ];

            $storage->put($cartData);

            $cartId = (int) $storage->getCartId();
            $storedItem = CartItemModel::where('cart_id', $cartId)->first();
            expect($storedItem->price)->toBe(29.99);
        });

        test('put handles Condition objects in global conditions', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $conditionMock = mock(Condition::class);
            $conditionMock->shouldReceive('toArray')->andReturn(['name' => 'global_discount', 'type' => 'percentage', 'value' => 20]);

            $cartData = [
                'items'      => [],
                'conditions' => [$conditionMock],
            ];

            $storage->put($cartData);

            $cartId = (int) $storage->getCartId();
            $cart = CartModel::find($cartId);
            expect($cart->conditions->toArray())->toHaveCount(1);
        });

        test('put handles single Condition object as global condition', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $conditionMock = mock(Condition::class);
            $conditionMock->shouldReceive('toArray')->andReturn(['name' => 'single_condition', 'type' => 'fixed', 'value' => 5]);

            $cartData = [
                'items'      => [],
                'conditions' => $conditionMock,
            ];

            $storage->put($cartData);

            $cartId = (int) $storage->getCartId();
            $cart = CartModel::find($cartId);
            expect($cart->conditions->toArray())->toHaveCount(1);
        });
    });

    describe('Data Removal (flush method)', function (): void {
        test('flush removes all cart items and clears conditions', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            // Add test data
            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item1',
                'name'       => 'Test Item',
                'price'      => 10.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [],
            ]);

            $cart = CartModel::find($cartId);
            $cart->conditions = [['name' => 'test', 'type' => 'fixed', 'value' => 5]];
            $cart->save();

            // Verify data exists
            expect(CartItemModel::where('cart_id', $cartId)->count())->toBe(1)
                ->and($cart->fresh()->conditions->toArray())->toHaveCount(1);

            // Flush the cart
            $storage->flush();

            // Verify data is removed
            expect(CartItemModel::where('cart_id', $cartId)->count())->toBe(0)
                ->and($cart->fresh()->conditions->toArray())->toBeEmpty();
        });

        test('flush only affects current cart', function (): void {
            // Create two different carts
            $cart1 = CartModel::create(['session_id' => 'session1']);
            $cart2 = CartModel::create(['session_id' => 'session2']);

            CartItemModel::create([
                'cart_id'    => $cart1->id,
                'item_id'    => 'item1',
                'name'       => 'Cart 1 Item',
                'price'      => 10.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [],
            ]);

            CartItemModel::create([
                'cart_id'    => $cart2->id,
                'item_id'    => 'item2',
                'name'       => 'Cart 2 Item',
                'price'      => 15.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [],
            ]);

            // Create storage for cart1 and flush it
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            // Manually set the cart ID to cart1 (simulate session)
            session(['laravel_session' => 'session1']);

            $storage->flush();

            // Cart2 items should still exist
            expect(CartItemModel::where('cart_id', $cart2->id)->count())->toBe(1);
        });
    });

    describe('Authentication Scenarios', function (): void {
        test('creates separate carts for different users', function (): void {
            // Create cart for user 1
            $cart1 = CartModel::create(['user_id' => 100]);

            // Create cart for user 2
            $cart2 = CartModel::create(['user_id' => 200]);

            expect($cart1->id)->not()->toBe($cart2->id)
                ->and($cart1->user_id)->toBe(100)
                ->and($cart2->user_id)->toBe(200);
        });

        test('creates separate carts for different sessions', function (): void {
            // Create cart for session 1
            $cart1 = CartModel::create(['session_id' => 'session_1']);

            // Create cart for session 2
            $cart2 = CartModel::create(['session_id' => 'session_2']);

            expect($cart1->id)->not()->toBe($cart2->id)
                ->and($cart1->session_id)->toBe('session_1')
                ->and($cart2->session_id)->toBe('session_2');
        });

        test('handles user ID of zero correctly', function (): void {
            Auth::shouldReceive('check')->andReturn(true);
            Auth::shouldReceive('id')->andReturn(0);

            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = $storage->getCartId();

            // Should treat user ID 0 as guest user
            $cart = CartModel::find((int) $cartId);
            expect($cart->user_id)->toBeNull()
                ->and($cart->session_id)->not()->toBeNull();
        });
    });

    describe('Condition Hydration', function (): void {
        test('get returns hydrated ConditionInterface objects for item conditions', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item1',
                'name'       => 'Test Item',
                'price'      => 20.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [
                    ['name' => 'item discount', 'type' => 'percentage', 'value' => -10, 'target' => 'item', 'order' => 0, 'taxable' => false, 'attributes' => []],
                    ['name' => 'surcharge', 'type' => 'fixed', 'value' => 5, 'target' => 'subtotal', 'order' => 1, 'taxable' => false, 'attributes' => []],
                ],
            ]);

            $cart = $storage->get();

            expect($cart['items']['item1']['conditions'])->toHaveCount(2)
                ->and($cart['items']['item1']['conditions'][0])->toBeInstanceOf(PercentageCondition::class)
                ->and($cart['items']['item1']['conditions'][0]->name)->toBe('item discount')
                ->and($cart['items']['item1']['conditions'][0]->value)->toBe(-10)
                ->and($cart['items']['item1']['conditions'][0]->target)->toBe(ConditionTarget::ITEM)
                ->and($cart['items']['item1']['conditions'][1])->toBeInstanceOf(FixedCondition::class)
                ->and($cart['items']['item1']['conditions'][1]->name)->toBe('surcharge');
        });

        test('get returns hydrated ConditionInterface objects for global conditions', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            $cart = CartModel::find($cartId);
            $cart->conditions = [
                ['name' => 'shipping', 'type' => 'fixed', 'value' => 5, 'target' => 'subtotal', 'order' => 0, 'taxable' => false, 'attributes' => []],
                ['name' => 'sales tax', 'type' => 'percentage', 'value' => 8.25, 'target' => 'taxable', 'order' => 1, 'taxable' => true, 'attributes' => []],
            ];
            $cart->save();

            $retrievedCart = $storage->get();

            expect($retrievedCart['conditions'])->toHaveCount(2)
                ->and($retrievedCart['conditions'][0])->toBeInstanceOf(FixedCondition::class)
                ->and($retrievedCart['conditions'][0]->name)->toBe('shipping')
                ->and($retrievedCart['conditions'][0]->target)->toBe(ConditionTarget::SUBTOTAL)
                ->and($retrievedCart['conditions'][1])->toBeInstanceOf(PercentageTaxCondition::class)
                ->and($retrievedCart['conditions'][1]->name)->toBe('sales tax')
                ->and($retrievedCart['conditions'][1]->target)->toBe(ConditionTarget::TAXABLE)
                ->and($retrievedCart['conditions'][1]->taxable)->toBeTrue();
        });

        test('getCartById returns hydrated ConditionInterface objects', function (): void {
            $cart = CartModel::create(['session_id' => 'hydration_test']);
            CartItemModel::create([
                'cart_id'    => $cart->id,
                'item_id'    => 'item1',
                'name'       => 'Test Item',
                'price'      => 10.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [
                    ['name' => 'discount', 'type' => 'percentage', 'value' => -15, 'target' => 'item', 'order' => 0, 'taxable' => false, 'attributes' => []],
                ],
            ]);
            $cart->conditions = [
                ['name' => 'flat fee', 'type' => 'fixed', 'value' => 3, 'target' => 'subtotal', 'order' => 0, 'taxable' => false, 'attributes' => []],
            ];
            $cart->save();

            $storage = new DatabaseStorage(new CartModel);
            $retrievedCart = $storage->getCartById((string) $cart->id);

            expect($retrievedCart['items']['item1']['conditions'][0])->toBeInstanceOf(PercentageCondition::class)
                ->and($retrievedCart['items']['item1']['conditions'][0]->name)->toBe('discount')
                ->and($retrievedCart['conditions'][0])->toBeInstanceOf(FixedCondition::class)
                ->and($retrievedCart['conditions'][0]->name)->toBe('flat fee');
        });

        test('round-trip: conditions survive put then get', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $discount = PercentageCondition::make([
                'name'   => 'sale',
                'value'  => -20,
                'target' => ConditionTarget::SUBTOTAL,
            ]);
            $shipping = FixedCondition::make([
                'name'   => 'shipping',
                'value'  => 7.50,
                'target' => ConditionTarget::SUBTOTAL,
            ]);

            $cartData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Widget',
                        'price'      => new Price(25.00),
                        'quantity'   => 2,
                        'attributes' => [],
                        'conditions' => [$discount],
                    ],
                ],
                'conditions' => [$shipping],
            ];

            $storage->put($cartData);
            $retrieved = $storage->get();

            // Item conditions are hydrated
            expect($retrieved['items']['item1']['conditions'])->toHaveCount(1)
                ->and($retrieved['items']['item1']['conditions'][0])->toBeInstanceOf(PercentageCondition::class)
                ->and($retrieved['items']['item1']['conditions'][0]->name)->toBe('sale')
                ->and($retrieved['items']['item1']['conditions'][0]->value)->toEqual(-20.0);

            // Global conditions are hydrated
            expect($retrieved['conditions'])->toHaveCount(1)
                ->and($retrieved['conditions'][0])->toBeInstanceOf(FixedCondition::class)
                ->and($retrieved['conditions'][0]->name)->toBe('shipping')
                ->and($retrieved['conditions'][0]->value)->toBe(7.50);
        });
    });

    describe('Edge Cases and Error Handling', function (): void {
        test('handles empty cart data gracefully', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $emptyCart = ['items' => [], 'conditions' => []];
            $result = $storage->put($emptyCart);

            expect($result)->toBe($emptyCart);

            $retrievedCart = $storage->get();
            expect($retrievedCart['items'])->toBeEmpty()
                ->and($retrievedCart['conditions'])->toBeEmpty();
        });

        test('handles null attributes and conditions', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Test Item',
                        'price'      => 10.00,
                        'quantity'   => 1,
                        'attributes' => null,
                        'conditions' => null,
                    ],
                ],
                'conditions' => null,
            ];

            $storage->put($cartData);

            $retrievedCart = $storage->get();
            expect($retrievedCart['items']['item1']['attributes'])->not()->toBeNull()
                ->and($retrievedCart['items']['item1']['conditions'])->not()->toBeNull();
        });

        test('handles empty conditions array correctly', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);
            $cartId = (int) $storage->getCartId();

            // Add item with empty conditions
            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'item1',
                'name'       => 'Test Item',
                'price'      => 10.00,
                'quantity'   => 1,
                'attributes' => [],
                'conditions' => [],
            ]);

            // Should handle empty conditions gracefully
            $cart = $storage->get();
            expect($cart)->toHaveKey('items')
                ->and($cart['items']['item1']['conditions'])->toBeArray()
                ->and($cart['items']['item1']['conditions'])->toBeEmpty();
        });

        test('handles very large quantities and prices', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Expensive Item',
                        'price'      => 999999.99,
                        'quantity'   => 1000000,
                        'attributes' => [],
                        'conditions' => [],
                    ],
                ],
                'conditions' => [],
            ];

            $storage->put($cartData);

            $retrievedCart = $storage->get();
            expect($retrievedCart['items']['item1']['price']->toFloat())->toBe(999999.99)
                ->and($retrievedCart['items']['item1']['quantity'])->toBe(1000000);
        });

        test('handles special characters in item names and attributes', function (): void {
            $cartModel = new CartModel;
            $storage = new DatabaseStorage($cartModel);

            $cartData = [
                'items' => [
                    'item1' => [
                        'name'       => 'Special Item with émojis 🛒 & symbols <>&"\'',
                        'price'      => 15.99,
                        'quantity'   => 1,
                        'attributes' => [
                            'description' => 'Contains special chars: <>&"\' and émojis 🎉',
                            'unicode'     => '测试中文字符',
                        ],
                        'conditions' => [],
                    ],
                ],
                'conditions' => [],
            ];

            $storage->put($cartData);

            $retrievedCart = $storage->get();
            expect($retrievedCart['items']['item1']['name'])->toBe('Special Item with émojis 🛒 & symbols <>&"\'')
                ->and($retrievedCart['items']['item1']['attributes']['unicode'])->toBe('测试中文字符');
        });
    });

    describe('End-to-End: Cart with DatabaseStorage', function (): void {
        test('Cart can add items and global conditions, persist, and reload with hydrated conditions', function (): void {
            $storage = new DatabaseStorage(new CartModel);

            // Build cart, add items and conditions
            $cart = new Cart($storage);
            $cart->addItem([
                'id' => 'widget', 'name' => 'Widget', 'price' => 50.00, 'quantity' => 2,
            ]);
            $cart->addItem([
                'id' => 'gadget', 'name' => 'Gadget', 'price' => 30.00, 'quantity' => 1,
            ]);
            $cart->addCondition(FixedCondition::make([
                'name' => 'Shipping', 'value' => 7.50, 'target' => ConditionTarget::SUBTOTAL,
            ]));
            $cart->addCondition(PercentageCondition::make([
                'name' => 'Coupon', 'value' => -10, 'target' => ConditionTarget::SUBTOTAL,
            ]));

            // Verify total on the live cart
            $originalTotal = $cart->total();

            // Reload from storage — Cart constructor now hydrates items automatically
            $reloadedCart = new Cart($storage);
            expect($reloadedCart->total()->toFloat())->toBe($originalTotal->toFloat());
        });

        test('Cart with item-level conditions survives database round-trip', function (): void {
            $storage = new DatabaseStorage(new CartModel);

            // Build cart with item-level conditions
            $cart = new Cart($storage);
            $cart->addItem([
                'id' => 'shirt', 'name' => 'T-Shirt', 'price' => 25.00, 'quantity' => 3,
            ]);
            $cart->addItemCondition('shirt', PercentageCondition::make([
                'name' => 'Clearance', 'value' => -20, 'target' => ConditionTarget::ITEM,
            ]));

            $originalTotal = $cart->total();

            // Verify conditions in storage are hydrated
            $rawData = $storage->get();
            $shirtConditions = $rawData['items']['shirt']['conditions'];
            expect($shirtConditions)->toHaveCount(1)
                ->and($shirtConditions[0])->toBeInstanceOf(PercentageCondition::class)
                ->and($shirtConditions[0]->name)->toBe('Clearance');

            // CartItem can be constructed from the stored data (conditions accepted)
            $cartItem = new CartItem($rawData['items']['shirt']);
            expect($cartItem->conditions)->toHaveCount(1)
                ->and($cartItem->conditions->first())->toBeInstanceOf(PercentageCondition::class);

            // Subtotal matches: 25 * 3 = 75, -20% per item = 25*0.8*3 = 60
            expect($cartItem->subtotal()->toFloat())->toBe(60.00);
        });

        test('Cart constructed from DatabaseStorage hydrates items as CartItem objects', function (): void {
            $storage = new DatabaseStorage(new CartModel);
            $cartId = (int) $storage->getCartId();

            // Seed items directly in the database
            CartItemModel::create([
                'cart_id'    => $cartId,
                'item_id'    => 'book',
                'name'       => 'PHP Book',
                'price'      => 40.00,
                'quantity'   => 2,
                'attributes' => ['format' => 'hardcover'],
                'conditions' => [],
            ]);

            // Construct a new Cart — items should be hydrated automatically
            $cart = new Cart($storage);

            expect($cart->items)->toHaveCount(1)
                ->and($cart->items->first())->toBeInstanceOf(CartItem::class)
                ->and($cart->items->first()->name)->toBe('PHP Book')
                ->and($cart->subtotal()->toFloat())->toBe(80.00)
                ->and($cart->total()->toFloat())->toBe(80.00);
        });
    });
});
