<?php

declare(strict_types=1);

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Fluent;

describe('CartItem', function (): void {
    beforeEach(function (): void {
        // Set default currency and locale for testing
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
        config(['flexicart.compound_discounts' => false]);
    });

    describe('Constructor', function (): void {
        test('can be instantiated with required parameters', function (): void {
            $item = new CartItem([
                'id'    => 1,
                'name'  => 'Test Product',
                'price' => 10.00,
            ]);

            expect($item->id)->toBe(1)
                ->and($item->name)->toBe('Test Product')
                ->and($item->price)->toBeInstanceOf(Price::class)
                ->and($item->price->formatted())->toBe('$10.00')
                ->and($item->quantity)->toBe(1)
                ->and($item->taxable)->toBeTrue()
                ->and($item->attributes)->toBeInstanceOf(Fluent::class)
                ->and($item->conditions)->toBeInstanceOf(\Illuminate\Support\Collection::class)
                ->and($item->conditions)->toHaveCount(0);
        });

        test('can be instantiated with all parameters', function (): void {
            $price = new Price(25.50);
            $attributes = ['color' => 'red', 'size' => 'large'];
            $condition = new FixedCondition('Discount', 5.00);

            $item = new CartItem([
                'id'         => 'product-123',
                'name'       => 'Test Product',
                'price'      => $price,
                'quantity'   => 3,
                'attributes' => $attributes,
                'conditions' => [$condition],
                'taxable'    => false,
            ]);

            expect($item->id)->toBe('product-123')
                ->and($item->name)->toBe('Test Product')
                ->and($item->price)->toBe($price)
                ->and($item->quantity)->toBe(3)
                ->and($item->taxable)->toBeFalse()
                ->and($item->attributes->toArray())->toBe($attributes)
                ->and($item->conditions)->toHaveCount(1)
                ->and($item->conditions->first())->toBe($condition);
        });

        test('can be instantiated with Fluent attributes', function (): void {
            $attributes = new Fluent(['color' => 'blue']);

            $item = new CartItem([
                'id'         => 1,
                'name'       => 'Test Product',
                'price'      => 10.00,
                'attributes' => $attributes,
            ]);

            expect($item->attributes)->toBe($attributes);
        });

        test('ensures minimum quantity of 1', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 10.00,
                'quantity' => 0,
            ]);

            expect($item->quantity)->toBe(1);
        });

        test('ensures minimum quantity of 1 with negative value', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 10.00,
                'quantity' => -5,
            ]);

            expect($item->quantity)->toBe(1);
        });

        test('converts numeric price to Price object', function (): void {
            $item = new CartItem([
                'id'    => 1,
                'name'  => 'Test Product',
                'price' => 15.99,
            ]);

            expect($item->price)->toBeInstanceOf(Price::class)
                ->and($item->price->formatted())->toBe('$15.99');
        });

        test('handles empty attributes array', function (): void {
            $item = new CartItem([
                'id'         => 1,
                'name'       => 'Test Product',
                'price'      => 10.00,
                'attributes' => [],
            ]);

            expect($item->attributes)->toBeInstanceOf(Fluent::class)
                ->and($item->attributes->toArray())->toBe([]);
        });

        test('ignores invalid conditions', function (): void {
            $validCondition = new FixedCondition('Valid', 5.00);

            $item = new CartItem([
                'id'         => 1,
                'name'       => 'Test Product',
                'price'      => 10.00,
                'conditions' => [$validCondition, 'invalid', null, []],
            ]);

            expect($item->conditions)->toHaveCount(1)
                ->and($item->conditions->first())->toBe($validCondition);
        });

        test('defaults taxable to true when not provided', function (): void {
            $item = new CartItem([
                'id'    => 1,
                'name'  => 'Test Product',
                'price' => 10.00,
            ]);

            expect($item->taxable)->toBeTrue();
        });
    });

    describe('Static make method', function (): void {
        test('can create instance with required parameters', function (): void {
            $item = CartItem::make([
                'id'    => 1,
                'name'  => 'Test Product',
                'price' => 10.00,
            ]);

            expect($item)->toBeInstanceOf(CartItem::class)
                ->and($item->id)->toBe(1)
                ->and($item->name)->toBe('Test Product')
                ->and($item->price->formatted())->toBe('$10.00');
        });

        test('can create instance with all parameters', function (): void {
            $attributes = ['color' => 'green'];
            $condition = new PercentageCondition('Discount', 10);

            $item = CartItem::make([
                'id'         => 'abc-123',
                'name'       => 'Test Product',
                'price'      => 50.00,
                'quantity'   => 2,
                'attributes' => $attributes,
                'conditions' => [$condition],
                'taxable'    => false,
            ]);

            expect($item->id)->toBe('abc-123')
                ->and($item->quantity)->toBe(2)
                ->and($item->taxable)->toBeFalse()
                ->and($item->attributes->toArray())->toBe($attributes)
                ->and($item->conditions->first())->toBe($condition);
        });
    });

    describe('Quantity Management', function (): void {
        test('can set quantity', function (): void {
            $item = new CartItem([
                'id'    => 1,
                'name'  => 'Test Product',
                'price' => 10.00,
            ]);

            $result = $item->setQuantity(5);

            expect($result)->toBe($item)
                ->and($item->quantity)->toBe(5);
        });

        test('converts negative quantity to positive', function (): void {
            $item = new CartItem([
                'id'    => 1,
                'name'  => 'Test Product',
                'price' => 10.00,
            ]);

            $item->setQuantity(-3);

            expect($item->quantity)->toBe(3);
        });

        test('handles zero quantity', function (): void {
            $item = new CartItem([
                'id'    => 1,
                'name'  => 'Test Product',
                'price' => 10.00,
            ]);

            $item->setQuantity(0);

            expect($item->quantity)->toBe(0);
        });
    });

    describe('Condition Management', function (): void {
        describe('addCondition', function (): void {
            test('can add condition object', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $condition = new FixedCondition('Discount', 2.00);
                $result = $item->addCondition($condition);

                expect($result)->toBe($item)
                    ->and($item->conditions)->toHaveCount(1)
                    ->and($item->conditions->first())->toBe($condition);
            });

            test('can add condition from array using FixedCondition', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $conditionArray = [
                    'name'  => 'Array Discount',
                    'value' => 3.00,
                ];

                $condition = FixedCondition::make($conditionArray);
                $item->addCondition($condition);

                expect($item->conditions)->toHaveCount(1)
                    ->and($item->conditions->first()->name)->toBe('Array Discount')
                    ->and($item->conditions->first()->value)->toBe(3.00);
            });

            test('overwrites condition with same name', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $condition1 = new FixedCondition('Discount', 2.00);
                $condition2 = new FixedCondition('Discount', 5.00);

                $item->addCondition($condition1);
                $item->addCondition($condition2);

                expect($item->conditions)->toHaveCount(1)
                    ->and($item->conditions->first())->toBe($condition2)
                    ->and($item->conditions->first()->value)->toBe(5.00);
            });

            test('adds multiple conditions with different names', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $condition1 = new FixedCondition('Discount 1', 2.00);
                $condition2 = new FixedCondition('Discount 2', 3.00);

                $item->addCondition($condition1);
                $item->addCondition($condition2);

                expect($item->conditions)->toHaveCount(2);
            });
        });

        describe('addConditions', function (): void {
            test('can add multiple conditions', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $conditions = [
                    new FixedCondition('Discount 1', 2.00),
                    new PercentageCondition('Discount 2', 10),
                    FixedCondition::make([
                        'name'  => 'Array Discount',
                        'value' => 1.00,
                    ]),
                ];

                $result = $item->addConditions($conditions);

                expect($result)->toBe($item)
                    ->and($item->conditions)->toHaveCount(3);
            });

            test('handles empty conditions array', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $item->addConditions([]);

                expect($item->conditions)->toHaveCount(0);
            });
        });

        describe('removeCondition', function (): void {
            test('can remove condition by name', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $condition1 = new FixedCondition('Keep', 2.00);
                $condition2 = new FixedCondition('Remove', 3.00);

                $item->addCondition($condition1);
                $item->addCondition($condition2);

                $result = $item->removeCondition('Remove');

                expect($result)->toBe($item)
                    ->and($item->conditions)->toHaveCount(1)
                    ->and($item->conditions->first()->name)->toBe('Keep');
            });

            test('handles removing non-existent condition', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $condition = new FixedCondition('Keep', 2.00);
                $item->addCondition($condition);

                $item->removeCondition('NonExistent');

                expect($item->conditions)->toHaveCount(1)
                    ->and($item->conditions->first()->name)->toBe('Keep');
            });

            test('maintains collection indexes after removal', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $item->addCondition(new FixedCondition('First', 1.00));
                $item->addCondition(new FixedCondition('Second', 2.00));
                $item->addCondition(new FixedCondition('Third', 3.00));

                $item->removeCondition('Second');

                expect($item->conditions)->toHaveCount(2)
                    ->and($item->conditions->keys()->toArray())->toBe([0, 1]);
            });
        });

        describe('clearConditions', function (): void {
            test('can clear all conditions', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $item->addCondition(new FixedCondition('Discount 1', 2.00));
                $item->addCondition(new FixedCondition('Discount 2', 3.00));

                $result = $item->clearConditions();

                expect($result)->toBe($item)
                    ->and($item->conditions)->toHaveCount(0);
            });

            test('handles clearing when no conditions exist', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 10.00,
                ]);

                $item->clearConditions();

                expect($item->conditions)->toHaveCount(0);
            });
        });
    });

    describe('Price Calculations', function (): void {
        describe('unitPrice', function (): void {
            test('returns the base price', function (): void {
                $price = new Price(15.99);
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => $price,
                ]);

                expect($item->unitPrice())->toBe($price);
            });
        });

        describe('unadjustedSubtotal', function (): void {
            test('calculates subtotal without conditions', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 3,
                ]);

                $subtotal = $item->unadjustedSubtotal();

                expect($subtotal->formatted())->toBe('$30.00');
            });

            test('handles quantity of 1', function (): void {
                $item = new CartItem([
                    'id'    => 1,
                    'name'  => 'Test Product',
                    'price' => 25.50,
                ]);

                $subtotal = $item->unadjustedSubtotal();

                expect($subtotal->formatted())->toBe('$25.50');
            });
        });

        describe('subtotal', function (): void {
            test('calculates subtotal without conditions', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$20.00');
            });

            test('calculates subtotal with fixed item condition', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $item->addCondition(new FixedCondition('Discount', -2.00, ConditionTarget::ITEM));

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$16.00'); // (10 - 2) * 2
            });

            test('calculates subtotal with percentage item condition', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $item->addCondition(new PercentageCondition('Discount', -10, ConditionTarget::ITEM));

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$18.00'); // (10 - 1) * 2
            });

            test('calculates subtotal with fixed subtotal condition', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $item->addCondition(new FixedCondition('Discount', -5.00, ConditionTarget::SUBTOTAL));

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$15.00'); // (10 * 2) - 5
            });

            test('calculates subtotal with percentage subtotal condition', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $item->addCondition(new PercentageCondition('Discount', -10, ConditionTarget::SUBTOTAL));

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$18.00'); // (10 * 2) - 2
            });

            test('calculates subtotal with multiple conditions', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $item->addCondition(new FixedCondition('Item Discount', -1.00, ConditionTarget::ITEM));
                $item->addCondition(new PercentageCondition('Subtotal Discount', -10, ConditionTarget::SUBTOTAL));

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$16.20'); // ((10 - 1) * 2) - 1.8
            });

            test('handles compound discounts when enabled', function (): void {
                config(['flexicart.compound_discounts' => true]);

                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 2,
                ]);

                $item->addCondition(new PercentageCondition('First Discount', -10, ConditionTarget::ITEM));
                $item->addCondition(new PercentageCondition('Second Discount', -10, ConditionTarget::ITEM));

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$16.20'); // ((10 * 0.9) * 0.9) * 2
            });

            test('prevents negative subtotal', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ]);

                $item->addCondition(new FixedCondition('Large Discount', -20.00, ConditionTarget::SUBTOTAL));

                $subtotal = $item->subtotal();

                expect($subtotal->formatted())->toBe('$0.00');
            });

            test('sorts conditions by target and order', function (): void {
                $item = new CartItem([
                    'id'       => 1,
                    'name'     => 'Test Product',
                    'price'    => 10.00,
                    'quantity' => 1,
                ]);

                // Add conditions in reverse order to test sorting
                $item->addCondition(new FixedCondition('Subtotal Second', -1.00, ConditionTarget::SUBTOTAL, order: 2));
                $item->addCondition(new FixedCondition('Item First', -1.00, ConditionTarget::ITEM, order: 1));
                $item->addCondition(new FixedCondition('Subtotal First', -1.00, ConditionTarget::SUBTOTAL, order: 1));

                $subtotal = $item->subtotal();

                // Item conditions should be applied first, then subtotal conditions in order
                expect($subtotal->formatted())->toBe('$7.00'); // (10 - 1) - 1 - 1
            });
        });
    });

    describe('Array Conversion', function (): void {
        test('converts to array with basic data', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 10.00,
                'quantity' => 2,
            ]);

            $array = $item->toArray();

            expect($array)->toHaveKeys(['id', 'name', 'price', 'quantity', 'unitPrice', 'subtotal', 'attributes', 'conditions'])
                ->and($array['id'])->toBe(1)
                ->and($array['name'])->toBe('Test Product')
                ->and($array['price'])->toBeInstanceOf(Price::class)
                ->and($array['quantity'])->toBe(2)
                ->and($array['unitPrice'])->toBeInstanceOf(Price::class)
                ->and($array['subtotal'])->toBeInstanceOf(Price::class)
                ->and($array['attributes'])->toBe([])
                ->and($array['conditions'])->toBe([]);
        });

        test('converts to array with attributes and conditions', function (): void {
            $attributes = ['color' => 'red', 'size' => 'large'];
            $condition = new FixedCondition('Discount', 2.00);

            $item = new CartItem([
                'id'         => 'abc-123',
                'name'       => 'Test Product',
                'price'      => 15.00,
                'quantity'   => 1,
                'attributes' => $attributes,
            ]);

            $item->addCondition($condition);

            $array = $item->toArray();

            expect($array['id'])->toBe('abc-123')
                ->and($array['attributes'])->toBe($attributes)
                ->and($array['conditions'])->toHaveCount(1)
                ->and($array['conditions'][0])->toBeArray()
                ->and($array['conditions'][0]['name'])->toBe('Discount');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles very large quantities', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 1.00,
                'quantity' => 999999,
            ]);

            $subtotal = $item->subtotal();

            expect($subtotal->formatted())->toBe('$999,999.00');
        });

        test('handles very small prices', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 0.01,
                'quantity' => 1,
            ]);

            $subtotal = $item->subtotal();

            expect($subtotal->formatted())->toBe('$0.01');
        });

        test('handles zero price', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Free Product',
                'price'    => 0.00,
                'quantity' => 5,
            ]);

            $subtotal = $item->subtotal();

            expect($subtotal->formatted())->toBe('$0.00');
        });

        test('handles complex condition combinations', function (): void {
            $item = new CartItem([
                'id'       => 1,
                'name'     => 'Test Product',
                'price'    => 100.00,
                'quantity' => 2,
            ]);

            // Mix of positive and negative conditions
            $item->addCondition(new FixedCondition('Fee', 5.00, ConditionTarget::ITEM));
            $item->addCondition(new PercentageCondition('Discount', -10, ConditionTarget::ITEM));
            $item->addCondition(new FixedCondition('Shipping', 10.00, ConditionTarget::SUBTOTAL));
            $item->addCondition(new PercentageCondition('Tax', 8.25, ConditionTarget::SUBTOTAL));

            $subtotal = $item->subtotal();

            // ((100 + 5 - 10) * 2) + 10 + (190 * 0.0825) = 190 + 10 + 15.675 = 215.675
            expect($subtotal->toFloat())->toBeGreaterThan(215.00)
                ->and($subtotal->toFloat())->toBeLessThan(216.00);
        });
    });
});
