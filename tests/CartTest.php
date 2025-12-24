<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageTaxCondition;
use Daikazu\Flexicart\Exceptions\CartException;
use Daikazu\Flexicart\Price;
use Daikazu\Flexicart\Tests\MockStorage;
use Illuminate\Support\Collection;

describe('Cart', function (): void {
    beforeEach(function (): void {
        // Set default currency and locale for testing
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
        config(['flexicart.compound_discounts' => false]);

        // Create a fresh mock storage for each test
        $this->mockStorage = new MockStorage;
        $this->cart = new Cart($this->mockStorage);
    });

    describe('Constructor and Factory Methods', function (): void {
        test('can be instantiated with storage', function (): void {
            $cart = new Cart($this->mockStorage);

            expect($cart)->toBeInstanceOf(Cart::class)
                ->and($cart->id())->toBe('mock-cart-id')
                ->and($cart->items())->toBeInstanceOf(Collection::class)
                ->and($cart->items())->toHaveCount(0)
                ->and($cart->isEmpty())->toBeTrue();
        });

        test('can get cart by ID when cart exists', function (): void {
            // Bind mock storage to the container
            app()->bind(\Daikazu\Flexicart\Contracts\StorageInterface::class, function () {
                $mockStorage = new MockStorage;
                $mockStorage->put([
                    'items' => [
                        'item1' => new CartItem([
                            'id'       => 'item1',
                            'name'     => 'Test Item',
                            'price'    => 10.00,
                            'quantity' => 2,
                        ]),
                    ],
                    'conditions' => [],
                ]);

                return $mockStorage;
            });

            $cart = Cart::getCartById('mock-cart-id');

            expect($cart)->toBeInstanceOf(Cart::class)
                ->and($cart->items())->toHaveCount(1)
                ->and($cart->item('item1'))->toBeInstanceOf(CartItem::class)
                ->and($cart->item('item1')->name)->toBe('Test Item');
        });

        test('returns null when getting cart by non-existent ID', function (): void {
            $cart = Cart::getCartById('non-existent-id');

            expect($cart)->toBeNull();
        });

        test('cart method returns self', function (): void {
            expect($this->cart->cart())->toBe($this->cart);
        });
    });

    describe('Item Management', function (): void {
        describe('Adding Items', function (): void {
            test('can add item as array', function (): void {
                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 25.99,
                    'quantity' => 2,
                ];

                $result = $this->cart->addItem($item);

                expect($result)->toBe($this->cart)
                    ->and($this->cart->items())->toHaveCount(1)
                    ->and($this->cart->item('product1'))->toBeInstanceOf(CartItem::class)
                    ->and($this->cart->item('product1')->name)->toBe('Test Product')
                    ->and($this->cart->item('product1')->quantity)->toBe(2);
            });

            test('can add item as CartItem object', function (): void {
                $cartItem = new CartItem([
                    'id'    => 'product2',
                    'name'  => 'Another Product',
                    'price' => 15.50,
                ]);

                $result = $this->cart->addItem($cartItem);

                expect($result)->toBe($this->cart)
                    ->and($this->cart->items())->toHaveCount(1)
                    ->and($this->cart->item('product2'))->toBe($cartItem);
            });

            test('throws exception when adding item without ID', function (): void {
                $item = [
                    'name'  => 'Test Product',
                    'price' => 25.99,
                ];

                expect(fn () => $this->cart->addItem($item))
                    ->toThrow(CartException::class, 'Item ID is required');
            });

            test('throws exception when adding item without name', function (): void {
                $item = [
                    'id'    => 'product1',
                    'price' => 25.99,
                ];

                expect(fn () => $this->cart->addItem($item))
                    ->toThrow(CartException::class, 'Item name is required');
            });

            test('throws exception when adding item without price', function (): void {
                $item = [
                    'id'   => 'product1',
                    'name' => 'Test Product',
                ];

                expect(fn () => $this->cart->addItem($item))
                    ->toThrow(CartException::class, 'Item price is required');
            });

            test('updates quantity when adding existing item', function (): void {
                $item = [
                    'id'       => 'product1',
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ];

                $this->cart->addItem($item);
                $this->cart->addItem($item);

                expect($this->cart->items())->toHaveCount(1)
                    ->and($this->cart->item('product1')->quantity)->toBe(4);
            });

            test('merges attributes when adding existing item', function (): void {
                $item1 = [
                    'id'         => 'product1',
                    'name'       => 'Test Product',
                    'price'      => 10.00,
                    'attributes' => ['color' => 'red'],
                ];

                $item2 = [
                    'id'         => 'product1',
                    'name'       => 'Test Product',
                    'price'      => 10.00,
                    'attributes' => ['size' => 'large'],
                ];

                $this->cart->addItem($item1);
                $this->cart->addItem($item2);

                $attributes = $this->cart->item('product1')->attributes->toArray();
                expect($attributes)->toBe(['color' => 'red', 'size' => 'large']);
            });
        });

        describe('Updating Items', function (): void {
            test('can update existing item', function (): void {
                $this->cart->addItem([
                    'id'       => 'product1',
                    'name'     => 'Original Name',
                    'price'    => 10.00,
                    'quantity' => 1,
                ]);

                $result = $this->cart->updateItem('product1', [
                    'name'     => 'Updated Name',
                    'price'    => 15.00,
                    'quantity' => 3,
                ]);

                expect($result)->toBe($this->cart)
                    ->and($this->cart->item('product1')->name)->toBe('Updated Name')
                    ->and($this->cart->item('product1')->unitPrice()->toFloat())->toBe(15.00)
                    ->and($this->cart->item('product1')->quantity)->toBe(3);
            });

            test('can update item taxable status', function (): void {
                $this->cart->addItem([
                    'id'      => 'product1',
                    'name'    => 'Test Product',
                    'price'   => 10.00,
                    'taxable' => true,
                ]);

                $this->cart->updateItem('product1', ['taxable' => false]);

                expect($this->cart->item('product1')->taxable)->toBeFalse();
            });

            test('can update item attributes', function (): void {
                $this->cart->addItem([
                    'id'         => 'product1',
                    'name'       => 'Test Product',
                    'price'      => 10.00,
                    'attributes' => ['color' => 'red'],
                ]);

                $this->cart->updateItem('product1', [
                    'attributes' => ['size' => 'large'],
                ]);

                $attributes = $this->cart->item('product1')->attributes->toArray();
                expect($attributes)->toBe(['size' => 'large']);
            });

            test('returns self when updating non-existent item', function (): void {
                $result = $this->cart->updateItem('non-existent', ['name' => 'New Name']);

                expect($result)->toBe($this->cart)
                    ->and($this->cart->items())->toHaveCount(0);
            });

            test('preserves existing conditions when updating item', function (): void {
                $condition = new FixedCondition('Discount', -5.00);

                $this->cart->addItem([
                    'id'         => 'product1',
                    'name'       => 'Test Product',
                    'price'      => 10.00,
                    'conditions' => [$condition],
                ]);

                $this->cart->updateItem('product1', ['name' => 'Updated Name']);

                expect($this->cart->item('product1')->conditions)->toHaveCount(1)
                    ->and($this->cart->item('product1')->conditions->first())->toBe($condition);
            });
        });

        describe('Removing Items', function (): void {
            test('can remove existing item', function (): void {
                $this->cart->addItem([
                    'id'    => 'product1',
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $result = $this->cart->removeItem('product1');

                expect($result)->toBe($this->cart)
                    ->and($this->cart->items())->toHaveCount(0)
                    ->and($this->cart->item('product1'))->toBeNull();
            });

            test('returns self when removing non-existent item', function (): void {
                $result = $this->cart->removeItem('non-existent');

                expect($result)->toBe($this->cart);
            });
        });

        describe('Clearing Cart', function (): void {
            test('can clear all items', function (): void {
                $this->cart->addItem([
                    'id'    => 'product1',
                    'name'  => 'Product 1',
                    'price' => 10.00,
                ]);

                $this->cart->addItem([
                    'id'    => 'product2',
                    'name'  => 'Product 2',
                    'price' => 20.00,
                ]);

                $result = $this->cart->clear();

                expect($result)->toBe($this->cart)
                    ->and($this->cart->items())->toHaveCount(0)
                    ->and($this->cart->isEmpty())->toBeTrue();
            });
        });
    });

    describe('Item Retrieval and Counting', function (): void {
        beforeEach(function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $this->cart->addItem([
                'id'       => 'product2',
                'name'     => 'Product 2',
                'price'    => 15.00,
                'quantity' => 3,
            ]);
        });

        test('can get specific item by ID', function (): void {
            $item = $this->cart->item('product1');

            expect($item)->toBeInstanceOf(CartItem::class)
                ->and($item->id)->toBe('product1')
                ->and($item->name)->toBe('Product 1');
        });

        test('returns null for non-existent item', function (): void {
            $item = $this->cart->item('non-existent');

            expect($item)->toBeNull();
        });

        test('can get all items', function (): void {
            $items = $this->cart->items();

            expect($items)->toBeInstanceOf(Collection::class)
                ->and($items)->toHaveCount(2)
                ->and($items->has('product1'))->toBeTrue()
                ->and($items->has('product2'))->toBeTrue();
        });

        test('can count total quantity', function (): void {
            expect($this->cart->count())->toBe(5); // 2 + 3
        });

        test('can count unique items', function (): void {
            expect($this->cart->uniqueCount())->toBe(2);
        });

        test('isEmpty returns false when cart has items', function (): void {
            expect($this->cart->isEmpty())->toBeFalse();
        });

        test('isEmpty returns true when cart is empty', function (): void {
            $emptyCart = new Cart(new MockStorage);
            expect($emptyCart->isEmpty())->toBeTrue();
        });
    });

    describe('Price Calculations', function (): void {
        describe('Subtotal Calculation', function (): void {
            test('calculates subtotal for single item', function (): void {
                $this->cart->addItem([
                    'id'       => 'product1',
                    'name'     => 'Product 1',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $subtotal = $this->cart->subtotal();

                expect($subtotal)->toBeInstanceOf(Price::class)
                    ->and($subtotal->toFloat())->toBe(20.00);
            });

            test('calculates subtotal for multiple items', function (): void {
                $this->cart->addItem([
                    'id'       => 'product1',
                    'name'     => 'Product 1',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $this->cart->addItem([
                    'id'       => 'product2',
                    'name'     => 'Product 2',
                    'price'    => 15.50,
                    'quantity' => 1,
                ]);

                $subtotal = $this->cart->subtotal();

                expect($subtotal->toFloat())->toBe(35.50); // (10 * 2) + (15.50 * 1)
            });

            test('returns zero for empty cart', function (): void {
                $subtotal = $this->cart->subtotal();

                expect($subtotal->toFloat())->toBe(0.00);
            });
        });

        describe('Taxable Subtotal Calculation', function (): void {
            test('calculates taxable subtotal for taxable items only', function (): void {
                $this->cart->addItem([
                    'id'       => 'product1',
                    'name'     => 'Taxable Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                    'taxable'  => true,
                ]);

                $this->cart->addItem([
                    'id'       => 'product2',
                    'name'     => 'Non-taxable Product',
                    'price'    => 15.00,
                    'quantity' => 1,
                    'taxable'  => false,
                ]);

                $taxableSubtotal = $this->cart->getTaxableSubtotal();

                expect($taxableSubtotal->toFloat())->toBe(20.00); // Only taxable item
            });

            test('returns zero when no taxable items', function (): void {
                $this->cart->addItem([
                    'id'      => 'product1',
                    'name'    => 'Non-taxable Product',
                    'price'   => 10.00,
                    'taxable' => false,
                ]);

                $taxableSubtotal = $this->cart->getTaxableSubtotal();

                expect($taxableSubtotal->toFloat())->toBe(0.00);
            });
        });

        describe('Total Calculation', function (): void {
            test('calculates total without conditions', function (): void {
                $this->cart->addItem([
                    'id'       => 'product1',
                    'name'     => 'Product 1',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $total = $this->cart->total();

                expect($total->toFloat())->toBe(20.00);
            });

            test('calculates total with fixed discount condition', function (): void {
                $this->cart->addItem([
                    'id'    => 'product1',
                    'name'  => 'Product 1',
                    'price' => 100.00,
                ]);

                $discount = new FixedCondition('Discount', -10.00);
                $this->cart->addCondition($discount);

                $total = $this->cart->total();

                expect($total->toFloat())->toBe(90.00);
            });

            test('calculates total with percentage discount condition', function (): void {
                $this->cart->addItem([
                    'id'    => 'product1',
                    'name'  => 'Product 1',
                    'price' => 100.00,
                ]);

                $discount = new PercentageCondition('Discount', -10); // 10% discount
                $this->cart->addCondition($discount);

                $total = $this->cart->total();

                expect($total->toFloat())->toBe(90.00);
            });

            test('calculates total with tax condition on taxable items', function (): void {
                $this->cart->addItem([
                    'id'      => 'product1',
                    'name'    => 'Taxable Product',
                    'price'   => 100.00,
                    'taxable' => true,
                ]);

                $this->cart->addItem([
                    'id'      => 'product2',
                    'name'    => 'Non-taxable Product',
                    'price'   => 50.00,
                    'taxable' => false,
                ]);

                $tax = new PercentageTaxCondition('Sales Tax', 10); // 10% tax
                $this->cart->addCondition($tax);

                $total = $this->cart->total();

                expect($total->toFloat())->toBe(160.00); // 150 + (100 * 0.10)
            });

            test('ensures total is never negative', function (): void {
                $this->cart->addItem([
                    'id'    => 'product1',
                    'name'  => 'Product 1',
                    'price' => 10.00,
                ]);

                $largeDiscount = new FixedCondition('Large Discount', -50.00);
                $this->cart->addCondition($largeDiscount);

                $total = $this->cart->total();

                expect($total->toFloat())->toBe(0.00);
            });

            test('calculates total with compound discounts enabled', function (): void {
                config(['flexicart.compound_discounts' => true]);

                $this->cart->addItem([
                    'id'    => 'product1',
                    'name'  => 'Product 1',
                    'price' => 100.00,
                ]);

                $discount1 = new PercentageCondition('First Discount', -10); // 10% off
                $discount2 = new PercentageCondition('Second Discount', -10); // 10% off remaining

                $this->cart->addCondition($discount1);
                $this->cart->addCondition($discount2);

                $total = $this->cart->total();

                // With compound discounts: 100 - 10 = 90, then 90 - 9 = 81
                expect($total->toFloat())->toBe(81.00);
            });
        });
    });

    describe('Condition Management', function (): void {
        describe('Global Conditions', function (): void {
            test('can add condition as object', function (): void {
                $condition = new FixedCondition('Discount', -10.00);

                $result = $this->cart->addCondition($condition);
                $conditions = $this->cart->getRawCartData()['conditions'];

                expect($result)->toBe($this->cart)
                    ->and($conditions)->toHaveCount(1)
                    ->and($conditions->first())->toBe($condition);
            });

            test('can add multiple global conditions', function (): void {
                $condition1 = new FixedCondition('Discount 1', -5.00);
                $condition2 = new PercentageCondition('Discount 2', -10);

                $result = $this->cart->addConditions([$condition1, $condition2]);
                $conditions = $this->cart->getRawCartData()['conditions'];

                expect($result)->toBe($this->cart)
                    ->and($conditions)->toHaveCount(2);
            });

            test('overwrites global condition with same name', function (): void {
                $condition1 = new FixedCondition('Discount', -5.00);
                $condition2 = new FixedCondition('Discount', -10.00);

                $this->cart->addCondition($condition1);
                $this->cart->addCondition($condition2);
                $conditions = $this->cart->getRawCartData()['conditions'];

                expect($conditions)->toHaveCount(1)
                    ->and($conditions->first())->toBe($condition2);
            });

            test('can remove global condition by name', function (): void {
                $condition = new FixedCondition('Discount', -10.00);
                $this->cart->addCondition($condition);

                $result = $this->cart->removeCondition('Discount');
                $conditions = $this->cart->getRawCartData()['conditions'];

                expect($result)->toBe($this->cart)
                    ->and($conditions)->toHaveCount(0);
            });

            test('can clear all global conditions', function (): void {
                $condition1 = new FixedCondition('Discount 1', -5.00);
                $condition2 = new PercentageCondition('Discount 2', -10);

                $this->cart->addCondition($condition1);
                $this->cart->addCondition($condition2);

                $result = $this->cart->clearConditions();
                $conditions = $this->cart->getRawCartData()['conditions'];

                expect($result)->toBe($this->cart)
                    ->and($conditions)->toHaveCount(0);
            });
        });

        describe('Item Conditions', function (): void {
            beforeEach(function (): void {
                $this->cart->addItem([
                    'id'    => 'product1',
                    'name'  => 'Product 1',
                    'price' => 100.00,
                ]);
            });

            test('can add condition to specific item', function (): void {
                $condition = new FixedCondition('Item Discount', -10.00);

                $result = $this->cart->addItemCondition('product1', $condition);

                expect($result)->toBe($this->cart)
                    ->and($this->cart->item('product1')->conditions)->toHaveCount(1)
                    ->and($this->cart->item('product1')->conditions->first())->toBe($condition);
            });

            test('returns self when adding condition to non-existent item', function (): void {
                $condition = new FixedCondition('Discount', -10.00);

                $result = $this->cart->addItemCondition('non-existent', $condition);

                expect($result)->toBe($this->cart);
            });

            test('can remove condition from specific item', function (): void {
                $condition = new FixedCondition('Item Discount', -10.00);
                $this->cart->addItemCondition('product1', $condition);

                $result = $this->cart->removeItemCondition('product1', 'Item Discount');

                expect($result)->toBe($this->cart)
                    ->and($this->cart->item('product1')->conditions)->toHaveCount(0);
            });

            test('returns self when removing condition from non-existent item', function (): void {
                $result = $this->cart->removeItemCondition('non-existent', 'Discount');

                expect($result)->toBe($this->cart);
            });
        });
    });

    describe('Data Retrieval', function (): void {
        test('can get raw cart data', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $condition = new FixedCondition('Discount', -5.00);
            $this->cart->addCondition($condition);

            $data = $this->cart->getRawCartData();

            expect($data)->toBeArray()
                ->and($data)->toHaveKeys(['items', 'subtotal', 'total', 'count', 'conditions'])
                ->and($data['items'])->toBeInstanceOf(Collection::class)
                ->and($data['subtotal'])->toBeInstanceOf(Price::class)
                ->and($data['total'])->toBeInstanceOf(Price::class)
                ->and($data['count'])->toBe(2)
                ->and($data['conditions'])->toBeInstanceOf(Collection::class);
        });
    });

    describe('Edge Cases and Error Handling', function (): void {
        test('handles empty cart calculations gracefully', function (): void {
            expect($this->cart->subtotal()->toFloat())->toBe(0.00)
                ->and($this->cart->getTaxableSubtotal()->toFloat())->toBe(0.00)
                ->and($this->cart->total()->toFloat())->toBe(0.00)
                ->and($this->cart->count())->toBe(0)
                ->and($this->cart->uniqueCount())->toBe(0);
        });

        test('handles conditions on empty cart', function (): void {
            $condition = new FixedCondition('Discount', -10.00);
            $this->cart->addCondition($condition);

            expect($this->cart->total()->toFloat())->toBe(0.00);
        });

        test('handles very large quantities at cart level', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 1.00,
                'quantity' => 999999,
            ]);

            expect($this->cart->count())->toBe(999999)
                ->and($this->cart->subtotal()->toFloat())->toBe(999999.00);
        });

        test('handles decimal prices correctly', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 9.99,
                'quantity' => 3,
            ]);

            expect($this->cart->subtotal()->toFloat())->toBe(29.97);
        });

        test('handles mixed taxable and non-taxable items with conditions', function (): void {
            $this->cart->addItem([
                'id'      => 'taxable1',
                'name'    => 'Taxable Product 1',
                'price'   => 50.00,
                'taxable' => true,
            ]);

            $this->cart->addItem([
                'id'      => 'taxable2',
                'name'    => 'Taxable Product 2',
                'price'   => 30.00,
                'taxable' => true,
            ]);

            $this->cart->addItem([
                'id'      => 'nontaxable1',
                'name'    => 'Non-taxable Product',
                'price'   => 20.00,
                'taxable' => false,
            ]);

            $discount = new PercentageCondition('Discount', -10); // 10% discount
            $tax = new PercentageTaxCondition('Tax', 8); // 8% tax on taxable items

            $this->cart->addCondition($discount);
            $this->cart->addCondition($tax);

            $subtotal = $this->cart->subtotal()->toFloat(); // 100.00
            $taxableSubtotal = $this->cart->getTaxableSubtotal()->toFloat(); // 80.00
            $total = $this->cart->total()->toFloat();

            expect($subtotal)->toBe(100.00)
                ->and($taxableSubtotal)->toBe(80.00);

            // Total should be: 100 - 10 (discount) + 6.4 (8% tax on taxable portion after discount) = 95.76
            expect($total)->toBe(95.76);
        });
    });

    describe('Data Persistence', function (): void {
        test('persists data when adding items', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 10.00,
            ]);

            $storedData = $this->mockStorage->get();

            expect($storedData['items'])->toHaveCount(1)
                ->and($storedData['items']->has('product1'))->toBeTrue();
        });

        test('persists data when updating items', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Original Name',
                'price' => 10.00,
            ]);

            $this->cart->updateItem('product1', ['name' => 'Updated Name']);

            $storedData = $this->mockStorage->get();

            expect($storedData['items']->get('product1')->name)->toBe('Updated Name');
        });

        test('persists data when removing items', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 10.00,
            ]);

            $this->cart->removeItem('product1');

            $storedData = $this->mockStorage->get();

            expect($storedData['items'])->toHaveCount(0);
        });

        test('persists data when clearing cart', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 10.00,
            ]);

            $this->cart->clear();

            $storedData = $this->mockStorage->get();

            expect($storedData['items'])->toHaveCount(0);
        });

        test('persists data when adding conditions', function (): void {
            $condition = new FixedCondition('Discount', -10.00);
            $this->cart->addCondition($condition);

            $storedData = $this->mockStorage->get();

            expect($storedData['conditions'])->toHaveCount(1)
                ->and($storedData['conditions']->first())->toBe($condition);
        });

        test('persists data when removing conditions', function (): void {
            $condition = new FixedCondition('Discount', -10.00);
            $this->cart->addCondition($condition);
            $this->cart->removeCondition('Discount');

            $storedData = $this->mockStorage->get();

            expect($storedData['conditions'])->toHaveCount(0);
        });
    });
});
