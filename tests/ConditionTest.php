<?php

declare(strict_types=1);

use Daikazu\Flexicart\Conditions\Condition;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\FixedTaxCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageTaxCondition;
use Daikazu\Flexicart\Enums\ConditionTarget;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Fluent;

describe('Conditions', function (): void {
    beforeEach(function (): void {
        // Set default currency and locale for testing
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
    });

    describe('FixedCondition', function (): void {

        test('can be instantiated with required parameters', function (): void {
            $condition = new FixedCondition('Test Discount', 10.00);

            expect($condition->name)->toBe('Test Discount')
                ->and($condition->value)->toBe(10.00)
                ->and($condition->type)->toBe(ConditionType::FIXED)
                ->and($condition->target)->toBe(ConditionTarget::SUBTOTAL)
                ->and($condition->order)->toBe(0)
                ->and($condition->taxable)->toBeFalse()
                ->and($condition->attributes)->toBeInstanceOf(Fluent::class);
        });

        test('can be instantiated with all parameters', function (): void {
            $attributes = ['description' => 'Test description'];
            $condition = new FixedCondition(
                name: 'Test Discount',
                value: 15.50,
                target: ConditionTarget::ITEM,
                attributes: $attributes,
                order: 5,
                taxable: true
            );

            expect($condition->name)->toBe('Test Discount')
                ->and($condition->value)->toBe(15.50)
                ->and($condition->type)->toBe(ConditionType::FIXED)
                ->and($condition->target)->toBe(ConditionTarget::ITEM)
                ->and($condition->order)->toBe(5)
                ->and($condition->taxable)->toBeTrue()
                ->and($condition->attributes->toArray())->toBe($attributes);
        });

        test('can be instantiated with Fluent attributes', function (): void {
            $attributes = new Fluent(['description' => 'Test description']);
            $condition = new FixedCondition('Test Discount', 10.00, attributes: $attributes);

            expect($condition->attributes)->toBe($attributes);
        });

        test('can be instantiated with integer value', function (): void {
            $condition = new FixedCondition('Test Discount', 10);

            expect($condition->value)->toBe(10);
        });

        test('can be instantiated with float value', function (): void {
            $condition = new FixedCondition('Test Discount', 10.50);

            expect($condition->value)->toBe(10.50);
        });

        describe('Static make method', function (): void {
            test('can create instance with required parameters', function (): void {
                $condition = FixedCondition::make([
                    'name'  => 'Test Discount',
                    'value' => 10.00,
                ]);

                expect($condition)->toBeInstanceOf(FixedCondition::class)
                    ->and($condition->name)->toBe('Test Discount')
                    ->and($condition->value)->toBe(10.00)
                    ->and($condition->type)->toBe(ConditionType::FIXED)
                    ->and($condition->target)->toBe(ConditionTarget::SUBTOTAL);
            });

            test('can create instance with all parameters', function (): void {
                $attributes = ['description' => 'Test description'];
                $condition = FixedCondition::make([
                    'name'       => 'Test Discount',
                    'value'      => 15.50,
                    'target'     => ConditionTarget::ITEM,
                    'attributes' => $attributes,
                    'order'      => 5,
                    'taxable'    => true,
                ]);

                expect($condition->name)->toBe('Test Discount')
                    ->and($condition->value)->toBe(15.50)
                    ->and($condition->target)->toBe(ConditionTarget::ITEM)
                    ->and($condition->order)->toBe(5)
                    ->and($condition->taxable)->toBeTrue()
                    ->and($condition->attributes->toArray())->toBe($attributes);
            });

            test('throws exception when name is missing', function (): void {
                expect(fn () => FixedCondition::make(['value' => 10.00]))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "name" is required and must be a non-empty string.');
            });

            test('throws exception when name is empty string', function (): void {
                expect(fn () => FixedCondition::make(['name' => '', 'value' => 10.00]))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "name" is required and must be a non-empty string.');
            });

            test('throws exception when name is not string', function (): void {
                expect(fn () => FixedCondition::make(['name' => 123, 'value' => 10.00]))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "name" is required and must be a non-empty string.');
            });

            test('throws exception when value is missing', function (): void {
                expect(fn () => FixedCondition::make(['name' => 'Test']))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "value" is required and must be a number (int or float).');
            });

            test('throws exception when value is not numeric', function (): void {
                expect(fn () => FixedCondition::make(['name' => 'Test', 'value' => 'invalid']))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "value" is required and must be a number (int or float).');
            });

            test('throws exception when target is invalid', function (): void {
                expect(fn () => FixedCondition::make([
                    'name'   => 'Test',
                    'value'  => 10.00,
                    'target' => 'invalid',
                ]))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "target" must be an instance of ConditionTarget enum.');
            });

            test('throws exception when attributes is invalid', function (): void {
                expect(fn () => FixedCondition::make([
                    'name'       => 'Test',
                    'value'      => 10.00,
                    'attributes' => 'invalid',
                ]))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "attributes" must be an array or Fluent instance.');
            });

            test('throws exception when order is not integer', function (): void {
                expect(fn () => FixedCondition::make([
                    'name'  => 'Test',
                    'value' => 10.00,
                    'order' => 'invalid',
                ]))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "order" must be an integer.');
            });

            test('throws exception when taxable is not boolean', function (): void {
                expect(fn () => FixedCondition::make([
                    'name'    => 'Test',
                    'value'   => 10.00,
                    'taxable' => 'invalid',
                ]))
                    ->toThrow(InvalidArgumentException::class, 'Parameter "taxable" must be a boolean.');
            });
        });

        describe('calculate method', function (): void {
            test('returns fixed price value', function (): void {
                $condition = new FixedCondition('Test Discount', 10.00);
                $result = $condition->calculate();

                expect($result)->toBeInstanceOf(Price::class)
                    ->and($result->formatted())->toBe('$10.00');
            });

            test('returns fixed price value regardless of input price', function (): void {
                $condition = new FixedCondition('Test Discount', 25.50);
                $inputPrice = new Price(100.00);
                $result = $condition->calculate($inputPrice);

                expect($result)->toBeInstanceOf(Price::class)
                    ->and($result->formatted())->toBe('$25.50');
            });

            test('works with integer values', function (): void {
                $condition = new FixedCondition('Test Discount', 15);
                $result = $condition->calculate();

                expect($result->formatted())->toBe('$15.00');
            });

            test('works with float values', function (): void {
                $condition = new FixedCondition('Test Discount', 12.75);
                $result = $condition->calculate();

                expect($result->formatted())->toBe('$12.75');
            });
        });

        describe('formattedValue method', function (): void {
            test('formats integer value correctly', function (): void {
                $condition = new FixedCondition('Test Discount', 10);

                expect($condition->formattedValue())->toBe('$10.00');
            });

            test('formats float value correctly', function (): void {
                $condition = new FixedCondition('Test Discount', 15.50);

                expect($condition->formattedValue())->toBe('$15.50');
            });

            test('formats zero value correctly', function (): void {
                $condition = new FixedCondition('Test Discount', 0);

                expect($condition->formattedValue())->toBe('$0.00');
            });
        });

        describe('toArray method', function (): void {
            test('converts to array with default values', function (): void {
                $condition = new FixedCondition('Test Discount', 10.00);
                $array = $condition->toArray();

                expect($array)->toBe([
                    'name'       => 'Test Discount',
                    'value'      => 10.00,
                    'type'       => 'fixed',
                    'target'     => 'subtotal',
                    'attributes' => [],
                    'order'      => 0,
                    'taxable'    => false,
                ]);
            });

            test('converts to array with custom values', function (): void {
                $attributes = ['description' => 'Test description'];
                $condition = new FixedCondition(
                    name: 'Test Discount',
                    value: 15.50,
                    target: ConditionTarget::ITEM,
                    attributes: $attributes,
                    order: 5,
                    taxable: true
                );
                $array = $condition->toArray();

                expect($array)->toBe([
                    'name'       => 'Test Discount',
                    'value'      => 15.50,
                    'type'       => 'fixed',
                    'target'     => 'item',
                    'attributes' => $attributes,
                    'order'      => 5,
                    'taxable'    => true,
                ]);
            });

            test('converts Fluent attributes to array', function (): void {
                $attributes = new Fluent(['description' => 'Test description']);
                $condition = new FixedCondition('Test Discount', 10.00, attributes: $attributes);
                $array = $condition->toArray();

                expect($array['attributes'])->toBe(['description' => 'Test description']);
            });
        });
    });

    describe('FixedTaxCondition', function (): void {

        test(' FixedTaxCondition can be instantiated with required parameters', function (): void {
            $condition = new FixedTaxCondition('Test Tax', 5.00);

            expect($condition->name)->toBe('Test Tax')
                ->and($condition->value)->toBe(5.00)
                ->and($condition->type)->toBe(ConditionType::FIXED)
                ->and($condition->target)->toBe(ConditionTarget::SUBTOTAL)
                ->and($condition->order)->toBe(0)
                ->and($condition->taxable)->toBeFalse();
        });

        describe('calculate method', function (): void {
            test('returns fixed tax value', function (): void {
                $condition = new FixedTaxCondition('Test Tax', 8.50);
                $result = $condition->calculate();

                expect($result)->toBeInstanceOf(Price::class)
                    ->and($result->formatted())->toBe('$8.50');
            });
        });

        describe('formattedValue method', function (): void {
            test('formats value correctly', function (): void {
                $condition = new FixedTaxCondition('Test Tax', 7.25);

                expect($condition->formattedValue())->toBe('$7.25');
            });
        });
    });

    describe('PercentageCondition', function (): void {
        test('PercentageCondition can be instantiated with required parameters', function (): void {
            $condition = new PercentageCondition('Test Discount', 10.0);

            expect($condition->name)->toBe('Test Discount')
                ->and($condition->value)->toBe(10.0)
                ->and($condition->type)->toBe(ConditionType::PERCENTAGE)
                ->and($condition->target)->toBe(ConditionTarget::SUBTOTAL);
        });

        describe('calculate method', function (): void {
            test('calculates percentage of given price', function (): void {
                $condition = new PercentageCondition('Test Discount', 10.0);
                $price = new Price(100.00);
                $result = $condition->calculate($price);

                expect($result)->toBeInstanceOf(Price::class)
                    ->and($result->formatted())->toBe('$10.00');
            });

            test('calculates percentage with decimal values', function (): void {
                $condition = new PercentageCondition('Test Discount', 15.5);
                $price = new Price(200.00);
                $result = $condition->calculate($price);

                expect($result->formatted())->toBe('$31.00');
            });

            test('calculates percentage with fractional results', function (): void {
                $condition = new PercentageCondition('Test Discount', 33.33);
                $price = new Price(100.00);
                $result = $condition->calculate($price);

                expect($result->formatted())->toBe('$33.33');
            });

            test('throws exception when price is null for PercentageCondition', function (): void {
                $condition = new PercentageCondition('Test Discount', 10.0);

                expect(fn () => $condition->calculate())
                    ->toThrow(PriceException::class, 'Price is required for percentage conditions.');
            });

            test('handles zero percentage', function (): void {
                $condition = new PercentageCondition('Test Discount', 0.0);
                $price = new Price(100.00);
                $result = $condition->calculate($price);

                expect($result->formatted())->toBe('$0.00');
            });

            test('handles 100 percentage', function (): void {
                $condition = new PercentageCondition('Test Discount', 100.0);
                $price = new Price(50.00);
                $result = $condition->calculate($price);

                expect($result->formatted())->toBe('$50.00');
            });
        });

        describe('formattedValue method', function (): void {
            test('formats integer percentage correctly', function (): void {
                $condition = new PercentageCondition('Test Discount', 10);

                expect($condition->formattedValue())->toBe('10%');
            });

            test('formats float percentage correctly', function (): void {
                $condition = new PercentageCondition('Test Discount', 15.5);

                expect($condition->formattedValue())->toBe('15.5%');
            });

            test('formats percentage with trailing zeros removed', function (): void {
                $condition = new PercentageCondition('Test Discount', 10.00);

                expect($condition->formattedValue())->toBe('10%');
            });

            test('formats percentage with one decimal place', function (): void {
                $condition = new PercentageCondition('Test Discount', 10.50);

                expect($condition->formattedValue())->toBe('10.5%');
            });

            test('formats zero percentage correctly', function (): void {
                $condition = new PercentageCondition('Test Discount', 0);

                expect($condition->formattedValue())->toBe('0%');
            });
        });
    });

    describe('PercentageTaxCondition', function (): void {

        test('PercentageTaxCondition can be instantiated with required parameters', function (): void {
            $condition = new PercentageTaxCondition('Test Tax', 8.25);

            expect($condition->name)->toBe('Test Tax')
                ->and($condition->value)->toBe(8.25)
                ->and($condition->type)->toBe(ConditionType::PERCENTAGE)
                ->and($condition->target)->toBe(ConditionTarget::TAXABLE);
        });

        test('has default target of TAXABLE', function (): void {
            $condition = new PercentageTaxCondition('Test Tax', 8.25);

            expect($condition->target)->toBe(ConditionTarget::TAXABLE);
        });

        test('ignores constructor target parameter due to class default', function (): void {
            $condition = new PercentageTaxCondition('Test Tax', 8.25, ConditionTarget::ITEM);

            expect($condition->target)->toBe(ConditionTarget::TAXABLE);
        });

        describe('Static make method', function (): void {
            test('ignores target parameter due to class default', function (): void {
                $condition = PercentageTaxCondition::make([
                    'name'   => 'Test Tax',
                    'value'  => 8.25,
                    'target' => ConditionTarget::ITEM,
                ]);

                expect($condition->target)->toBe(ConditionTarget::TAXABLE);
            });
        });

        describe('calculate method', function (): void {
            test('calculates tax percentage of given price', function (): void {
                $condition = new PercentageTaxCondition('Test Tax', 8.25);
                $price = new Price(100.00);
                $result = $condition->calculate($price);

                expect($result)->toBeInstanceOf(Price::class)
                    ->and($result->formatted())->toBe('$8.25');
            });

            test('throws exception when price is null for PercentageTaxCondition', function (): void {
                $condition = new PercentageTaxCondition('Test Tax', 8.25);

                expect(fn () => $condition->calculate())
                    ->toThrow(PriceException::class, 'Price is required for percentage conditions.');
            });
        });

        describe('formattedValue method', function (): void {
            test('formats percentage correctly', function (): void {
                $condition = new PercentageTaxCondition('Test Tax', 8.25);

                expect($condition->formattedValue())->toBe('8.25%');
            });
        });

        describe('toArray method', function (): void {
            test('includes TAXABLE target in array', function (): void {
                $condition = new PercentageTaxCondition('Test Tax', 8.25);
                $array = $condition->toArray();

                expect($array['target'])->toBe('taxable');
            });
        });
    });

    describe('Property Defaults and Inheritance', function (): void {
        test('FixedCondition uses default SUBTOTAL target when none provided', function (): void {
            $condition = new FixedCondition('Test', 10.00);

            expect($condition->target)->toBe(ConditionTarget::SUBTOTAL);
        });

        test('PercentageCondition uses default SUBTOTAL target when none provided', function (): void {
            $condition = new PercentageCondition('Test', 10.0);

            expect($condition->target)->toBe(ConditionTarget::SUBTOTAL);
        });

        test('PercentageTaxCondition uses class-level TAXABLE target', function (): void {
            $condition = new PercentageTaxCondition('Test', 10.0);

            expect($condition->target)->toBe(ConditionTarget::TAXABLE);
        });

        test('constructor target parameter is used when no class default exists', function (): void {
            $condition = new FixedCondition('Test', 10.00, ConditionTarget::ITEM);

            expect($condition->target)->toBe(ConditionTarget::ITEM);
        });

        test('class-level target takes precedence over constructor parameter', function (): void {
            $condition = new PercentageTaxCondition('Test', 10.0, ConditionTarget::ITEM);

            expect($condition->target)->toBe(ConditionTarget::TAXABLE);
        });
    });

    describe('Condition::fromArray()', function (): void {
        test('reconstructs FixedCondition with subtotal target', function (): void {
            $original = new FixedCondition('Shipping', 5.00, ConditionTarget::SUBTOTAL, ['note' => 'flat rate'], 2, true);
            $restored = Condition::fromArray($original->toArray());

            expect($restored)->toBeInstanceOf(FixedCondition::class)
                ->and($restored->name)->toBe('Shipping')
                ->and($restored->value)->toBe(5.00)
                ->and($restored->type)->toBe(ConditionType::FIXED)
                ->and($restored->target)->toBe(ConditionTarget::SUBTOTAL)
                ->and($restored->order)->toBe(2)
                ->and($restored->taxable)->toBeTrue()
                ->and($restored->attributes->toArray())->toBe(['note' => 'flat rate']);
        });

        test('reconstructs FixedCondition with item target', function (): void {
            $restored = Condition::fromArray([
                'name'   => 'Item Add-on', 'value' => 3.00, 'type' => 'fixed',
                'target' => 'item', 'order' => 0, 'taxable' => false, 'attributes' => [],
            ]);

            expect($restored)->toBeInstanceOf(FixedCondition::class)
                ->and($restored->target)->toBe(ConditionTarget::ITEM);
        });

        test('reconstructs PercentageCondition', function (): void {
            $original = new PercentageCondition('Sale', -15.5, ConditionTarget::ITEM);
            $restored = Condition::fromArray($original->toArray());

            expect($restored)->toBeInstanceOf(PercentageCondition::class)
                ->and($restored->name)->toBe('Sale')
                ->and($restored->value)->toBe(-15.5)
                ->and($restored->type)->toBe(ConditionType::PERCENTAGE)
                ->and($restored->target)->toBe(ConditionTarget::ITEM);
        });

        test('reconstructs FixedTaxCondition from taxable target + fixed type', function (): void {
            $restored = Condition::fromArray([
                'name'   => 'Flat Tax', 'value' => 2.00, 'type' => 'fixed',
                'target' => 'taxable', 'order' => 0, 'taxable' => false, 'attributes' => [],
            ]);

            expect($restored)->toBeInstanceOf(FixedTaxCondition::class)
                ->and($restored->target)->toBe(ConditionTarget::TAXABLE);
        });

        test('reconstructs PercentageTaxCondition from taxable target + percentage type', function (): void {
            $original = new PercentageTaxCondition('Sales Tax', 8.25);
            $restored = Condition::fromArray($original->toArray());

            expect($restored)->toBeInstanceOf(PercentageTaxCondition::class)
                ->and($restored->name)->toBe('Sales Tax')
                ->and($restored->value)->toBe(8.25)
                ->and($restored->type)->toBe(ConditionType::PERCENTAGE)
                ->and($restored->target)->toBe(ConditionTarget::TAXABLE);
        });

        test('restored conditions are functional (calculate works)', function (): void {
            $percentData = (new PercentageCondition('10% off', -10, ConditionTarget::SUBTOTAL))->toArray();
            $fixedData = (new FixedCondition('Shipping', 5.00, ConditionTarget::SUBTOTAL))->toArray();

            $percent = Condition::fromArray($percentData);
            $fixed = Condition::fromArray($fixedData);

            expect($percent->calculate(new Price(100.00))->toFloat())->toBe(-10.00)
                ->and($fixed->calculate()->toFloat())->toBe(5.00);
        });

        test('defaults target to SUBTOTAL when target is missing', function (): void {
            $restored = Condition::fromArray([
                'name' => 'No Target', 'value' => 5.00, 'type' => 'fixed',
            ]);

            expect($restored)->toBeInstanceOf(FixedCondition::class)
                ->and($restored->target)->toBe(ConditionTarget::SUBTOTAL);
        });

        test('throws exception when type is missing', function (): void {
            expect(fn () => Condition::fromArray(['name' => 'Bad', 'value' => 5]))
                ->toThrow(InvalidArgumentException::class, 'Condition array must contain a string "type" field.');
        });

        test('throws exception for unknown type', function (): void {
            expect(fn () => Condition::fromArray(['name' => 'Bad', 'value' => 5, 'type' => 'unknown']))
                ->toThrow(InvalidArgumentException::class, 'Unknown condition type: unknown');
        });

        test('round-trips all four condition types through toArray/fromArray', function (): void {
            $conditions = [
                new FixedCondition('Fixed', 10.00, ConditionTarget::SUBTOTAL),
                new PercentageCondition('Percent', -20, ConditionTarget::ITEM),
                new FixedTaxCondition('FixedTax', 3.00, ConditionTarget::TAXABLE),
                new PercentageTaxCondition('PercentTax', 8.25),
            ];

            foreach ($conditions as $original) {
                $restored = Condition::fromArray($original->toArray());

                expect($restored)->toBeInstanceOf($original::class)
                    ->and($restored->name)->toBe($original->name)
                    ->and($restored->value)->toBe($original->value)
                    ->and($restored->type)->toBe($original->type)
                    ->and($restored->target)->toBe($original->target);
            }
        });
    });

    describe('Edge Cases and Error Handling', function (): void {
        test('handles very small percentage values', function (): void {
            $condition = new PercentageCondition('Test', 0.01);
            $price = new Price(1000.00);
            $result = $condition->calculate($price);

            expect($result->formatted())->toBe('$0.10');
        });

        test('handles very large percentage values', function (): void {
            $condition = new PercentageCondition('Test', 500.0);
            $price = new Price(100.00);
            $result = $condition->calculate($price);

            expect($result->formatted())->toBe('$500.00');
        });

        test('handles negative values in fixed conditions', function (): void {
            $condition = new FixedCondition('Test', -10.00);
            $result = $condition->calculate();

            expect($result->formatted())->toBe('-$10.00');
        });

        test('handles negative percentage values', function (): void {
            $condition = new PercentageCondition('Test', -10.0);
            $price = new Price(100.00);
            $result = $condition->calculate($price);

            expect($result->formatted())->toBe('-$10.00');
        });

        test('attributes can be empty array', function (): void {
            $condition = new FixedCondition('Test', 10.00, attributes: []);

            expect($condition->attributes->toArray())->toBe([]);
        });

        test('order can be negative', function (): void {
            $condition = new FixedCondition('Test', 10.00, order: -5);

            expect($condition->order)->toBe(-5);
        });
    });
});
