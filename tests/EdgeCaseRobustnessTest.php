<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageTaxCondition;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Models\CartItemModel;
use Daikazu\Flexicart\Models\CartModel;
use Daikazu\Flexicart\Storage\DatabaseStorage;
use Daikazu\Flexicart\Tests\MockStorage;
use Illuminate\Support\Facades\Auth;

describe('CartItem Behavior', function (): void {
    beforeEach(function (): void {
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
        config(['flexicart.compound_discounts' => false]);

        $this->mockStorage = new MockStorage;
        $this->cart = new Cart($this->mockStorage);
    });

    describe('Condition Immutability', function (): void {
        test('conditions should not be reordered as a side effect of calling subtotal()', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 100.00,
                'quantity' => 1,
            ]);

            // Add conditions in specific order: Subtotal first, then Item
            $subtotalCondition = new FixedCondition('Subtotal Discount', -5.00, ConditionTarget::SUBTOTAL, order: 2);
            $itemCondition = new FixedCondition('Item Discount', -10.00, ConditionTarget::ITEM, order: 1);

            $item->addCondition($subtotalCondition);
            $item->addCondition($itemCondition);

            // Record initial condition order
            $initialOrder = $item->conditions->pluck('name')->toArray();
            expect($initialOrder)->toBe(['Subtotal Discount', 'Item Discount']);

            // Call subtotal() - this should NOT change the condition order
            $item->subtotal();

            // Verify conditions are NOT mutated after calling subtotal()
            $afterOrder = $item->conditions->pluck('name')->toArray();

            // After fix: Conditions should maintain their original order
            expect($afterOrder)->toBe($initialOrder)
                ->and($afterOrder)->toBe(['Subtotal Discount', 'Item Discount']);
        });

        test('multiple subtotal calls should not change condition order', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 100.00,
                'quantity' => 1,
            ]);

            $item->addCondition(new FixedCondition('C', -1.00, ConditionTarget::SUBTOTAL, order: 3));
            $item->addCondition(new FixedCondition('A', -2.00, ConditionTarget::ITEM, order: 1));
            $item->addCondition(new FixedCondition('B', -3.00, ConditionTarget::ITEM, order: 2));

            // Record original order
            $originalOrder = $item->conditions->pluck('name')->toArray();
            expect($originalOrder)->toBe(['C', 'A', 'B']);

            // First call should not change order
            $item->subtotal();
            $firstCallOrder = $item->conditions->pluck('name')->toArray();
            expect($firstCallOrder)->toBe($originalOrder);

            // Second call should also not change order
            $item->subtotal();
            $secondCallOrder = $item->conditions->pluck('name')->toArray();
            expect($secondCallOrder)->toBe($originalOrder);
        });

        test('subtotal calculation result is correct with conditions in any order', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 100.00,
                'quantity' => 2,
            ]);

            // Add in reverse order to test sorting
            $item->addCondition(new FixedCondition('Subtotal Discount', -10.00, ConditionTarget::SUBTOTAL));
            $item->addCondition(new FixedCondition('Item Discount', -5.00, ConditionTarget::ITEM));

            $subtotal = $item->subtotal();

            // Should be: (100 - 5) * 2 - 10 = 190 - 10 = 180
            expect($subtotal->toFloat())->toBe(180.00);
        });
    });

    describe('Precision Handling', function (): void {
        test('precision with repeating decimals in percentage', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 100.00,
                'quantity' => 3,
            ]);

            // 33.333...% discount
            $item->addCondition(new PercentageCondition('Third Off', -33.333333, ConditionTarget::SUBTOTAL));

            $subtotal = $item->subtotal();

            // Brick/Money should handle this precisely
            expect($subtotal->toFloat())->toBeGreaterThan(199.99)
                ->and($subtotal->toFloat())->toBeLessThan(200.01);
        });

        test('precision with very small percentages', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 1000000.00, // $1M
            ]);

            // 0.001% discount = $10
            $discount = new PercentageCondition('Tiny Discount', -0.001);
            $this->cart->addCondition($discount);

            $total = $this->cart->total();

            // Should be 1000000 - 10 = 999990
            expect($total->toFloat())->toBe(999990.00);
        });

        test('precision with cumulative percentage calculations', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            // Add many small discounts that should sum to exactly 10%
            for ($i = 1; $i <= 100; $i++) {
                $discount = new PercentageCondition("Discount{$i}", -0.1); // 0.1% each
                $this->cart->addCondition($discount);
            }

            // 100 * 0.1% = 10% discount on $100 = $10 discount = $90 total
            expect($this->cart->total()->toFloat())->toBe(90.00);
        });

        test('taxable ratio calculation precision', function (): void {
            // This tests the float division in Cart::total() lines 539-540
            $this->cart->addItem([
                'id'      => 'taxable',
                'name'    => 'Taxable Item',
                'price'   => 33.33,
                'taxable' => true,
            ]);

            $this->cart->addItem([
                'id'      => 'nontaxable',
                'name'    => 'Non-taxable Item',
                'price'   => 66.67,
                'taxable' => false,
            ]);

            // Add a subtotal discount that's marked as taxable
            $discount = new PercentageCondition('Discount', -10.00);
            $discount->taxable = true;

            $tax = new PercentageTaxCondition('Tax', 10.00);

            $this->cart->addCondition($discount);
            $this->cart->addCondition($tax);

            // Subtotal = 33.33 + 66.67 = 100.00
            // Taxable subtotal = 33.33
            // Discount = -10% of 100 = -10.00
            // Taxable portion of discount = 10.00 * (33.33/100) = 3.333
            // Adjusted taxable = 33.33 - 3.333 = 29.997
            // Tax = 10% of ~30 = ~3
            // Total should be around 93

            $total = $this->cart->total();
            expect($total->toFloat())->toBeGreaterThan(92.00)
                ->and($total->toFloat())->toBeLessThan(94.00);
        });
    });

    describe('Negative Total Prevention', function (): void {
        test('large discount on cart returns zero not negative', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 50.00,
            ]);

            // Apply $100 fixed discount on $50 item
            $discount = new FixedCondition('Huge Discount', -100.00);
            $this->cart->addCondition($discount);

            // Should return 0, not -50
            expect($this->cart->total()->toFloat())->toBe(0.00);
        });

        test('large percentage discount on item returns zero not negative', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 100.00,
                'quantity' => 1,
            ]);

            // 200% discount should not result in negative
            $item->addCondition(new PercentageCondition('Double Discount', -200, ConditionTarget::ITEM));

            expect($item->subtotal()->toFloat())->toBe(0.00);
        });

        test('multiple discounts exceeding total return zero', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            // Multiple discounts that exceed total
            $this->cart->addCondition(new PercentageCondition('Discount 1', -50));
            $this->cart->addCondition(new FixedCondition('Discount 2', -60.00));

            // 100 - 50 - 60 = -10 should become 0
            expect($this->cart->total()->toFloat())->toBe(0.00);
        });

        test('tax applied after discount exceeding subtotal', function (): void {
            $this->cart->addItem([
                'id'      => 'product1',
                'name'    => 'Product 1',
                'price'   => 100.00,
                'taxable' => true,
            ]);

            // Discount more than item value
            $this->cart->addCondition(new FixedCondition('Huge Discount', -150.00));
            // Tax should not create negative total
            $this->cart->addCondition(new PercentageTaxCondition('Tax', 10));

            expect($this->cart->total()->toFloat())->toBe(0.00);
        });
    });
});

describe('Database Serialization', function (): void {
    beforeEach(function (): void {
        Auth::shouldReceive('check')->andReturn(false)->byDefault();
        Auth::shouldReceive('id')->andReturn(null)->byDefault();

        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing']);

        $this->app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $this->app['config']->set('flexicart.storage', 'database');
        $this->app['config']->set('flexicart.currency', 'USD');
        $this->app['config']->set('flexicart.locale', 'en_US');
    });

    test('fixed condition survives database round-trip', function (): void {
        $originalCondition = new FixedCondition('Test Discount', -10.00, ConditionTarget::SUBTOTAL);

        // Store condition in database
        $cart = CartModel::create(['session_id' => 'test_session']);
        CartItemModel::create([
            'cart_id'    => $cart->id,
            'item_id'    => 'item1',
            'name'       => 'Test Item',
            'price'      => 100.00,
            'quantity'   => 1,
            'attributes' => [],
            'conditions' => [$originalCondition->toArray()],
        ]);

        // Retrieve and reconstruct
        $storage = new DatabaseStorage(new CartModel);
        $cartData = $storage->getCartById((string) $cart->id);

        expect($cartData)->not->toBeNull()
            ->and($cartData['items'])->toHaveKey('item1');

        // Get the reconstructed item and its conditions (returned as array)
        $retrievedItem = $cartData['items']['item1'];
        expect($retrievedItem['conditions'])->toHaveCount(1);

        // Conditions are returned as hydrated objects
        $retrievedCondition = $retrievedItem['conditions'][0];
        // Note: JSON serialization may convert -10.00 to int -10, so use toEqual for loose comparison
        expect($retrievedCondition)->toBeInstanceOf(FixedCondition::class)
            ->and($retrievedCondition->name)->toBe('Test Discount')
            ->and($retrievedCondition->value)->toEqual(-10.00)
            ->and($retrievedCondition->target)->toBe(ConditionTarget::SUBTOTAL);
    });

    test('percentage condition survives database round-trip', function (): void {
        $originalCondition = new PercentageCondition('Test Percent', -15.5, ConditionTarget::ITEM);

        $cart = CartModel::create(['session_id' => 'test_session_2']);
        CartItemModel::create([
            'cart_id'    => $cart->id,
            'item_id'    => 'item2',
            'name'       => 'Test Item 2',
            'price'      => 200.00,
            'quantity'   => 2,
            'attributes' => ['color' => 'red'],
            'conditions' => [$originalCondition->toArray()],
        ]);

        $storage = new DatabaseStorage(new CartModel);
        $cartData = $storage->getCartById((string) $cart->id);

        $retrievedItem = $cartData['items']['item2'];
        $retrievedCondition = $retrievedItem['conditions'][0];

        expect($retrievedCondition)->toBeInstanceOf(PercentageCondition::class)
            ->and($retrievedCondition->name)->toBe('Test Percent')
            ->and($retrievedCondition->value)->toBe(-15.5)
            ->and($retrievedCondition->target)->toBe(ConditionTarget::ITEM);
    });

    test('multiple conditions survive database round-trip', function (): void {
        $conditions = [
            (new FixedCondition('Fixed', -5.00, ConditionTarget::ITEM))->toArray(),
            (new PercentageCondition('Percent', -10, ConditionTarget::SUBTOTAL))->toArray(),
        ];

        $cart = CartModel::create(['session_id' => 'test_session_3']);
        CartItemModel::create([
            'cart_id'    => $cart->id,
            'item_id'    => 'item3',
            'name'       => 'Test Item 3',
            'price'      => 100.00,
            'quantity'   => 1,
            'attributes' => [],
            'conditions' => $conditions,
        ]);

        $storage = new DatabaseStorage(new CartModel);
        $cartData = $storage->getCartById((string) $cart->id);

        $retrievedItem = $cartData['items']['item3'];
        expect($retrievedItem['conditions'])->toHaveCount(2);

        $names = array_map(fn ($c) => $c->name, $retrievedItem['conditions']);
        expect($names)->toContain('Fixed')
            ->and($names)->toContain('Percent');
    });

    test('condition with custom order survives database round-trip', function (): void {
        $condition = new FixedCondition('Ordered Discount', -10.00, ConditionTarget::SUBTOTAL, order: 5);

        $cart = CartModel::create(['session_id' => 'test_session_4']);
        CartItemModel::create([
            'cart_id'    => $cart->id,
            'item_id'    => 'item4',
            'name'       => 'Test Item 4',
            'price'      => 100.00,
            'quantity'   => 1,
            'attributes' => [],
            'conditions' => [$condition->toArray()],
        ]);

        $storage = new DatabaseStorage(new CartModel);
        $cartData = $storage->getCartById((string) $cart->id);

        $retrievedCondition = $cartData['items']['item4']['conditions'][0];
        expect($retrievedCondition)->toBeInstanceOf(FixedCondition::class)
            ->and($retrievedCondition->order)->toBe(5);
    });

    test('cart-level conditions survive database round-trip', function (): void {
        $cartCondition = new PercentageCondition('Cart Discount', -20, ConditionTarget::SUBTOTAL);

        $cart = CartModel::create([
            'session_id' => 'test_session_5',
            'conditions' => [$cartCondition->toArray()],
        ]);

        CartItemModel::create([
            'cart_id'    => $cart->id,
            'item_id'    => 'item5',
            'name'       => 'Test Item 5',
            'price'      => 100.00,
            'quantity'   => 1,
            'attributes' => [],
            'conditions' => [],
        ]);

        $storage = new DatabaseStorage(new CartModel);
        $cartData = $storage->getCartById((string) $cart->id);

        expect($cartData['conditions'])->toHaveCount(1);

        // Cart conditions are returned as hydrated objects
        // Note: JSON serialization may convert -20.0 to int -20, so use toEqual for loose comparison
        $retrievedCondition = $cartData['conditions'][0];
        expect($retrievedCondition)->toBeInstanceOf(PercentageCondition::class)
            ->and($retrievedCondition->name)->toBe('Cart Discount')
            ->and($retrievedCondition->value)->toEqual(-20.0);
    });
});

describe('PercentageCondition Type Safety', function (): void {
    test('PercentageCondition should handle float vs int values correctly', function (): void {
        // This tests whether the lack of strict_types causes any issues
        $floatCondition = new PercentageCondition('Float Discount', -10.5);
        $intCondition = new PercentageCondition('Int Discount', -10);

        $item = new CartItem([
            'id'       => 1,
            'name'     => 'Test Product',
            'price'    => 100.00,
            'quantity' => 1,
        ]);

        // Both should work correctly
        $floatResult = $floatCondition->calculate($item->price);
        $intResult = $intCondition->calculate($item->price);

        expect($floatResult->toFloat())->toBe(-10.50)
            ->and($intResult->toFloat())->toBe(-10.00);
    });

    test('PercentageCondition formattedValue works correctly', function (): void {
        $wholePercent = new PercentageCondition('Whole', -10);
        $decimalPercent = new PercentageCondition('Decimal', -10.5);
        $longDecimalPercent = new PercentageCondition('Long', -10.123456);

        expect($wholePercent->formattedValue())->toBe('-10%')
            ->and($decimalPercent->formattedValue())->toBe('-10.5%')
            ->and($longDecimalPercent->formattedValue())->toBe('-10.12%');
    });
});
