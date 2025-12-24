<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Conditions\Types\PercentageTaxCondition;
use Daikazu\Flexicart\Price;
use Daikazu\Flexicart\Tests\MockStorage;

describe('Cart Calculation Edge Cases and Complex Scenarios', function (): void {
    beforeEach(function (): void {
        // Set default currency and locale for testing
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
        config(['flexicart.compound_discounts' => false]);

        // Create a fresh mock storage for each test
        $this->mockStorage = new MockStorage;
        $this->cart = new Cart($this->mockStorage);
    });

    describe('Zero and Negative Price Edge Cases', function (): void {
        test('handles zero price items correctly', function (): void {
            $this->cart->addItem([
                'id'       => 'free_item',
                'name'     => 'Free Item',
                'price'    => 0.00,
                'quantity' => 5,
            ]);

            expect($this->cart->subtotal()->toFloat())->toBe(0.00)
                ->and($this->cart->total()->toFloat())->toBe(0.00)
                ->and($this->cart->count())->toBe(5);
        });

        test('handles zero price items with conditions', function (): void {
            $this->cart->addItem([
                'id'    => 'free_item',
                'name'  => 'Free Item',
                'price' => 0.00,
            ]);

            $discount = new FixedCondition('Discount', -10.00);
            $tax = new PercentageTaxCondition('Tax', 10);

            $this->cart->addCondition($discount);
            $this->cart->addCondition($tax);

            expect($this->cart->total()->toFloat())->toBe(0.00);
        });

        test('handles mixed zero and positive price items', function (): void {
            $this->cart->addItem([
                'id'    => 'free_item',
                'name'  => 'Free Item',
                'price' => 0.00,
            ]);

            $this->cart->addItem([
                'id'    => 'paid_item',
                'name'  => 'Paid Item',
                'price' => 50.00,
            ]);

            $discount = new PercentageCondition('Discount', -20);
            $this->cart->addCondition($discount);

            expect($this->cart->subtotal()->toFloat())->toBe(50.00)
                ->and($this->cart->total()->toFloat())->toBe(40.00);
        });
    });

    describe('Extreme Quantity Edge Cases', function (): void {
        test('handles zero quantity items (converts to minimum quantity 1)', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 10.00,
                'quantity' => 0,
            ]);

            // Zero quantity gets converted to 1 by the system
            expect($this->cart->subtotal()->toFloat())->toBe(10.00)
                ->and($this->cart->total()->toFloat())->toBe(10.00)
                ->and($this->cart->count())->toBe(1)
                ->and($this->cart->uniqueCount())->toBe(1);
        });

        test('handles fractional quantities (gets truncated to integer)', function (): void {
            // Fractional quantities get cast to integers
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 10.00,
                'quantity' => 2.5,
            ]);

            // 2.5 becomes 2
            expect($this->cart->subtotal()->toFloat())->toBe(20.00);
        });

        test('handles very small decimal quantities (converts to minimum quantity 1)', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 100.00,
                'quantity' => 0.001,
            ]);

            // 0.001 becomes 0 when cast to int, then becomes 1 due to max(1, ...)
            expect($this->cart->subtotal()->toFloat())->toBe(100.00);
        });

        test('handles negative quantities (converts to minimum quantity 1)', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 10.00,
                'quantity' => -5,
            ]);

            // Negative quantities in constructor become max(1, -5) = 1
            expect($this->cart->subtotal()->toFloat())->toBe(10.00)
                ->and($this->cart->count())->toBe(1);
        });
    });

    describe('Precision and Rounding Edge Cases', function (): void {
        test('handles very small price differences', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 0.01, // Use a more realistic small price
            ]);

            $this->cart->addItem([
                'id'    => 'product2',
                'name'  => 'Product 2',
                'price' => 0.02,
            ]);

            expect($this->cart->subtotal()->toFloat())->toBe(0.03);
        });

        test('handles complex percentage calculations with rounding', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 33.33,
                'quantity' => 3,
            ]);

            $discount = new PercentageCondition('Discount', -33.333333);
            $this->cart->addCondition($discount);

            $total = $this->cart->total()->toFloat();
            expect($total)->toBeFloat()
                ->and($total)->toBeGreaterThan(66.00)
                ->and($total)->toBeLessThan(67.00);
        });

        test('handles multiple small percentage discounts', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            $discount1 = new PercentageCondition('Discount1', -0.1); // 0.1%
            $discount2 = new PercentageCondition('Discount2', -0.2); // 0.2%
            $discount3 = new PercentageCondition('Discount3', -0.3); // 0.3%

            $this->cart->addCondition($discount1);
            $this->cart->addCondition($discount2);
            $this->cart->addCondition($discount3);

            expect($this->cart->total()->toFloat())->toBe(99.40); // 100 - 0.6
        });
    });

    describe('Complex Multi-Item Scenarios', function (): void {
        test('handles cart with many different item types and conditions', function (): void {
            // Regular taxable item
            $this->cart->addItem([
                'id'       => 'regular1',
                'name'     => 'Regular Item 1',
                'price'    => 25.00,
                'quantity' => 2,
                'taxable'  => true,
            ]);

            // Non-taxable item
            $this->cart->addItem([
                'id'       => 'nontax1',
                'name'     => 'Non-taxable Item',
                'price'    => 15.00,
                'quantity' => 1,
                'taxable'  => false,
            ]);

            // Free item
            $this->cart->addItem([
                'id'       => 'free1',
                'name'     => 'Free Item',
                'price'    => 0.00,
                'quantity' => 3,
                'taxable'  => true,
            ]);

            // High-value item
            $this->cart->addItem([
                'id'       => 'expensive1',
                'name'     => 'Expensive Item',
                'price'    => 999.99,
                'quantity' => 1,
                'taxable'  => true,
            ]);

            // Add global conditions
            $globalDiscount = new PercentageCondition('Global Discount', -10);
            $salesTax = new PercentageTaxCondition('Sales Tax', 8.25);

            $this->cart->addCondition($globalDiscount);
            $this->cart->addCondition($salesTax);

            // Add item-specific condition
            $itemDiscount = new FixedCondition('Item Discount', -50.00);
            $this->cart->addItemCondition('expensive1', $itemDiscount);

            // Item calculations:
            // regular1: 25 * 2 = 50 (taxable)
            // nontax1: 15 * 1 = 15 (non-taxable)
            // free1: 0 * 3 = 0 (taxable)
            // expensive1: (999.99 - 50) * 1 = 949.99 (taxable, with item discount)
            // Subtotal with item conditions: 50 + 15 + 0 + 949.99 = 1014.99
            $subtotal = $this->cart->subtotal()->toFloat();
            $taxableSubtotal = $this->cart->getTaxableSubtotal()->toFloat(); // 50 + 0 + 949.99 = 999.99
            $total = $this->cart->total()->toFloat();

            expect($subtotal)->toBe(1014.99)
                ->and($taxableSubtotal)->toBe(999.99)
                ->and($total)->toBeFloat();
        });

        test('handles cart with items having individual conditions', function (): void {
            $this->cart->addItem([
                'id'    => 'item1',
                'name'  => 'Item 1',
                'price' => 100.00,
            ]);

            $this->cart->addItem([
                'id'    => 'item2',
                'name'  => 'Item 2',
                'price' => 200.00,
            ]);

            $this->cart->addItem([
                'id'    => 'item3',
                'name'  => 'Item 3',
                'price' => 300.00,
            ]);

            // Different conditions for each item
            $discount1 = new PercentageCondition('Item1 Discount', -10);
            $discount2 = new FixedCondition('Item2 Discount', -25.00);
            $discount3 = new PercentageCondition('Item3 Discount', -5);

            $this->cart->addItemCondition('item1', $discount1);
            $this->cart->addItemCondition('item2', $discount2);
            $this->cart->addItemCondition('item3', $discount3);

            // Item-specific conditions affect each item's subtotal
            // Item1: 100 - 10% = 90, Item2: 200 - 25 = 175, Item3: 300 - 5% = 285
            // Cart subtotal includes these adjustments: 90 + 175 + 285 = 550
            expect($this->cart->subtotal()->toFloat())->toBe(550.00)
                ->and($this->cart->total()->toFloat())->toBe(550.00);
        });
    });

    describe('Condition Order and Priority Edge Cases', function (): void {
        test('handles conditions with different order values', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            // Create conditions with specific order
            $discount1 = new PercentageCondition('First Discount', -10);
            $discount1->order = 1;

            $discount2 = new PercentageCondition('Second Discount', -5);
            $discount2->order = 2;

            $discount3 = new FixedCondition('Third Discount', -5.00);
            $discount3->order = 0; // Should be applied first

            $this->cart->addCondition($discount1);
            $this->cart->addCondition($discount2);
            $this->cart->addCondition($discount3);

            $total = $this->cart->total()->toFloat();
            expect($total)->toBeFloat();
        });

        test('handles conditions with same order values', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            $discount1 = new PercentageCondition('Discount A', -10);
            $discount1->order = 1;

            $discount2 = new PercentageCondition('Discount B', -10);
            $discount2->order = 1;

            $this->cart->addCondition($discount1);
            $this->cart->addCondition($discount2);

            expect($this->cart->total()->toFloat())->toBe(80.00); // 100 - 10 - 10
        });
    });

    describe('Currency and Locale Edge Cases', function (): void {
        test('handles different currency configurations', function (): void {
            config(['flexicart.currency' => 'EUR']);

            $cart = new Cart(new MockStorage);
            $cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            expect($cart->subtotal())->toBeInstanceOf(Price::class)
                ->and($cart->subtotal()->toFloat())->toBe(100.00);
        });

        test('handles very large monetary values', function (): void {
            $this->cart->addItem([
                'id'       => 'expensive',
                'name'     => 'Very Expensive Item',
                'price'    => 999999.99,
                'quantity' => 1,
            ]);

            $discount = new PercentageCondition('Discount', -1);
            $this->cart->addCondition($discount);

            $total = $this->cart->total()->toFloat();
            expect($total)->toBeFloat()
                ->and($total)->toBeGreaterThan(989999.00);
        });
    });

    describe('Boundary Value Testing', function (): void {
        test('handles maximum safe integer quantities', function (): void {
            $this->cart->addItem([
                'id'       => 'product1',
                'name'     => 'Product 1',
                'price'    => 0.01,
                'quantity' => PHP_INT_MAX,
            ]);

            expect($this->cart->count())->toBe(PHP_INT_MAX);
        });

        test('handles 100% discount scenarios', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            $discount = new PercentageCondition('Full Discount', -100);
            $this->cart->addCondition($discount);

            expect($this->cart->total()->toFloat())->toBe(0.00);
        });

        test('handles over 100% discount scenarios', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 100.00,
            ]);

            $discount = new PercentageCondition('Over Discount', -150);
            $this->cart->addCondition($discount);

            // Total should never go below 0
            expect($this->cart->total()->toFloat())->toBe(0.00);
        });
    });

    describe('Complex Tax Scenarios', function (): void {
        test('handles multiple tax rates on different items', function (): void {
            $this->cart->addItem([
                'id'      => 'food',
                'name'    => 'Food Item',
                'price'   => 50.00,
                'taxable' => true,
            ]);

            $this->cart->addItem([
                'id'      => 'luxury',
                'name'    => 'Luxury Item',
                'price'   => 100.00,
                'taxable' => true,
            ]);

            // Different tax rates
            $foodTax = new PercentageTaxCondition('Food Tax', 5);
            $luxuryTax = new PercentageTaxCondition('Luxury Tax', 15);

            $this->cart->addItemCondition('food', $foodTax);
            $this->cart->addItemCondition('luxury', $luxuryTax);

            $total = $this->cart->total()->toFloat();
            // Food: 50, Luxury: 100, Subtotal: 150
            // Tax conditions are applied at cart level, not item level for this calculation
            expect($total)->toBe(150.00);
        });

        test('handles tax on discounted amounts', function (): void {
            $this->cart->addItem([
                'id'      => 'product1',
                'name'    => 'Product 1',
                'price'   => 100.00,
                'taxable' => true,
            ]);

            $discount = new PercentageCondition('Discount', -20);
            $tax = new PercentageTaxCondition('Tax', 10);

            $this->cart->addCondition($discount);
            $this->cart->addCondition($tax);

            // Should be: 100 - 20 = 80, then tax on 80 = 8, total = 88
            expect($this->cart->total()->toFloat())->toBe(88.00);
        });
    });

    describe('Performance and Stress Testing', function (): void {
        test('handles cart with many items efficiently', function (): void {
            // Add 100 items to test performance
            for ($i = 1; $i <= 100; $i++) {
                $this->cart->addItem([
                    'id'    => "product{$i}",
                    'name'  => "Product {$i}",
                    'price' => $i * 1.00,
                ]);
            }

            expect($this->cart->uniqueCount())->toBe(100)
                ->and($this->cart->count())->toBe(100)
                ->and($this->cart->subtotal()->toFloat())->toBe(5050.00); // Sum of 1 to 100
        });

        test('handles cart with many conditions efficiently', function (): void {
            $this->cart->addItem([
                'id'    => 'product1',
                'name'  => 'Product 1',
                'price' => 1000.00,
            ]);

            // Add 50 small percentage discounts
            for ($i = 1; $i <= 50; $i++) {
                $discount = new PercentageCondition("Discount{$i}", -0.1); // 0.1% each
                $this->cart->addCondition($discount);
            }

            $total = $this->cart->total()->toFloat();
            expect($total)->toBe(950.00); // 1000 - (50 * 1) = 950
        });
    });
});
