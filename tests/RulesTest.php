<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Conditions\Rules\BuyXGetYRule;
use Daikazu\Flexicart\Conditions\Rules\ItemQuantityRule;
use Daikazu\Flexicart\Conditions\Rules\ThresholdRule;
use Daikazu\Flexicart\Conditions\Rules\TieredRule;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Events\RuleAdded;
use Daikazu\Flexicart\Events\RuleRemoved;
use Daikazu\Flexicart\Events\RulesCleared;
use Daikazu\Flexicart\Price;
use Daikazu\Flexicart\Tests\MockStorage;
use Illuminate\Support\Facades\Event;

describe('Rules Engine', function (): void {
    beforeEach(function (): void {
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
        config(['flexicart.events.enabled' => true]);

        $this->mockStorage = new MockStorage;
        $this->cart = new Cart($this->mockStorage);
    });

    describe('BuyXGetYRule', function (): void {
        test('applies when cart has enough items for buy X get Y', function (): void {
            // Buy 2 Get 1 Free requires 3 items minimum
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free',
                buyQuantity: 2,
                getQuantity: 1,
                getDiscount: 100.0
            );

            $this->cart->addItem(['id' => 'shirt', 'name' => 'T-Shirt', 'price' => 20.00, 'quantity' => 3]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
        });

        test('does not apply when cart has insufficient items', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free',
                buyQuantity: 2,
                getQuantity: 1,
                getDiscount: 100.0
            );

            $this->cart->addItem(['id' => 'shirt', 'name' => 'T-Shirt', 'price' => 20.00, 'quantity' => 2]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeFalse();
        });

        test('calculates discount for buy 2 get 1 free', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free',
                buyQuantity: 2,
                getQuantity: 1,
                getDiscount: 100.0
            );

            $this->cart->addItem(['id' => 'shirt', 'name' => 'T-Shirt', 'price' => 20.00, 'quantity' => 3]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // One item at $20 should be free
            expect($discount->toFloat())->toBe(-20.00);
        });

        test('calculates discount for partial percentage off', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 3 Get 1 50% Off',
                buyQuantity: 3,
                getQuantity: 1,
                getDiscount: 50.0
            );

            $this->cart->addItem(['id' => 'shirt', 'name' => 'T-Shirt', 'price' => 40.00, 'quantity' => 4]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // One item at $40 should be 50% off = $20 discount
            expect($discount->toFloat())->toBe(-20.00);
        });

        test('applies multiple bundles when quantity allows', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free',
                buyQuantity: 2,
                getQuantity: 1,
                getDiscount: 100.0
            );

            $this->cart->addItem(['id' => 'shirt', 'name' => 'T-Shirt', 'price' => 30.00, 'quantity' => 6]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // 6 items = 2 bundles = 2 free items at $30 each = $60 discount
            expect($discount->toFloat())->toBe(-60.00);
        });

        test('discounts cheapest items first', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free',
                buyQuantity: 2,
                getQuantity: 1,
                getDiscount: 100.0
            );

            $this->cart->addItem(['id' => 'expensive', 'name' => 'Expensive Shirt', 'price' => 50.00, 'quantity' => 2]);
            $this->cart->addItem(['id' => 'cheap', 'name' => 'Cheap Shirt', 'price' => 10.00, 'quantity' => 1]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // Cheapest item ($10) should be free
            expect($discount->toFloat())->toBe(-10.00);
        });

        test('can filter by specific item IDs', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free on Shirts',
                buyQuantity: 2,
                getQuantity: 1,
                getDiscount: 100.0,
                itemIds: ['shirt-*']
            );

            $this->cart->addItem(['id' => 'shirt-blue', 'name' => 'Blue Shirt', 'price' => 20.00, 'quantity' => 2]);
            $this->cart->addItem(['id' => 'shirt-red', 'name' => 'Red Shirt', 'price' => 25.00, 'quantity' => 1]);
            $this->cart->addItem(['id' => 'pants-jeans', 'name' => 'Jeans', 'price' => 50.00, 'quantity' => 2]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
            $discount = $rule->getDiscount();
            // Cheapest matching shirt ($20) should be free
            expect($discount->toFloat())->toBe(-20.00);
        });

        test('returns zero discount when no matching items', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free on Shirts',
                buyQuantity: 2,
                getQuantity: 1,
                getDiscount: 100.0,
                itemIds: ['shirt-*']
            );

            $this->cart->addItem(['id' => 'pants-jeans', 'name' => 'Jeans', 'price' => 50.00, 'quantity' => 3]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeFalse();
            expect($rule->getDiscount()->toFloat())->toBe(0.0);
        });
    });

    describe('ThresholdRule', function (): void {
        test('applies when subtotal exceeds minimum', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 50.00, 'quantity' => 3]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
        });

        test('does not apply when subtotal is below minimum', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 30.00, 'quantity' => 2]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeFalse();
        });

        test('calculates percentage discount correctly', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 50.00, 'quantity' => 4]);

            $subtotal = $this->cart->subtotal(); // $200
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // 10% of $200 = $20 discount
            expect($discount->toFloat())->toBe(-20.00);
        });

        test('calculates fixed discount correctly', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get $25 Off',
                minSubtotal: 100.00,
                discount: -25.0,
                discountType: ConditionType::FIXED
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 50.00, 'quantity' => 4]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            expect($discount->toFloat())->toBe(-25.00);
        });

        test('applies at exact threshold amount', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 100.00, 'quantity' => 1]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
            expect($rule->getDiscount()->toFloat())->toBe(-10.00);
        });

        test('formats value correctly for percentage', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE
            );

            expect($rule->formattedValue())->toContain('10%');
            expect($rule->formattedValue())->toContain('100');
        });
    });

    describe('TieredRule', function (): void {
        test('applies highest applicable tier', function (): void {
            $rule = new TieredRule(
                name: 'Volume Discount',
                tiers: [
                    100 => 5,
                    200 => 10,
                    500 => 15,
                ]
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 100.00, 'quantity' => 3]);

            $subtotal = $this->cart->subtotal(); // $300
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
            $discount = $rule->getDiscount();

            // $300 qualifies for 10% tier (not 15% which requires $500)
            expect($discount->toFloat())->toBe(-30.00);
        });

        test('does not apply when below all tiers', function (): void {
            $rule = new TieredRule(
                name: 'Volume Discount',
                tiers: [
                    100 => 5,
                    200 => 10,
                    500 => 15,
                ]
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 20.00, 'quantity' => 2]);

            $subtotal = $this->cart->subtotal(); // $40
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeFalse();
            expect($rule->getDiscount()->toFloat())->toBe(0.0);
        });

        test('applies lowest tier when just above threshold', function (): void {
            $rule = new TieredRule(
                name: 'Volume Discount',
                tiers: [
                    100 => 5,
                    200 => 10,
                    500 => 15,
                ]
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 101.00, 'quantity' => 1]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
            $discount = $rule->getDiscount();

            // 5% of $101 = $5.05
            expect($discount->toFloat())->toBe(-5.05);
        });

        test('applies highest tier when above all thresholds', function (): void {
            $rule = new TieredRule(
                name: 'Volume Discount',
                tiers: [
                    100 => 5,
                    200 => 10,
                    500 => 15,
                ]
            );

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 200.00, 'quantity' => 4]);

            $subtotal = $this->cart->subtotal(); // $800
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // 15% of $800 = $120
            expect($discount->toFloat())->toBe(-120.00);
        });

        test('getTiers returns all tiers for display', function (): void {
            $rule = new TieredRule(
                name: 'Volume Discount',
                tiers: [
                    100 => 5,
                    200 => 10,
                ]
            );

            $tiers = $rule->getTiers();

            expect($tiers)->toHaveCount(2);
            expect($tiers[0]['threshold'])->toBe(100.0);
            expect($tiers[0]['discount'])->toBe(5.0);
        });
    });

    describe('ItemQuantityRule', function (): void {
        test('applies when item quantity meets minimum', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Buy 5+ Get 10% Off',
                minQuantity: 5,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE
            );

            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 10.00, 'quantity' => 5]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
        });

        test('does not apply when quantity is below minimum', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Buy 5+ Get 10% Off',
                minQuantity: 5,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE
            );

            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 10.00, 'quantity' => 4]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeFalse();
        });

        test('calculates percentage discount on matching items', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Buy 5+ Get 10% Off',
                minQuantity: 5,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE,
                itemIds: ['widget']
            );

            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 20.00, 'quantity' => 5]);
            $this->cart->addItem(['id' => 'gadget', 'name' => 'Gadget', 'price' => 50.00, 'quantity' => 2]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // 10% of $100 (5 widgets at $20) = $10 discount
            expect($discount->toFloat())->toBe(-10.00);
        });

        test('calculates fixed discount once per cart', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Buy 5+ Get $15 Off',
                minQuantity: 5,
                discount: -15.0,
                discountType: ConditionType::FIXED,
                itemIds: '*',
                perItem: false
            );

            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 20.00, 'quantity' => 6]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            expect($discount->toFloat())->toBe(-15.00);
        });

        test('calculates fixed discount per item when perItem is true', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Buy 5+ Get $2 Off Each',
                minQuantity: 5,
                discount: -2.0,
                discountType: ConditionType::FIXED,
                itemIds: '*',
                perItem: true
            );

            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 20.00, 'quantity' => 6]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $discount = $rule->getDiscount();

            // $2 off per item * 6 items = $12 discount
            expect($discount->toFloat())->toBe(-12.00);
        });

        test('uses wildcard to match all items', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Buy 5+ Get 10% Off',
                minQuantity: 5,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE,
                itemIds: '*'
            );

            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 20.00, 'quantity' => 3]);
            $this->cart->addItem(['id' => 'gadget', 'name' => 'Gadget', 'price' => 30.00, 'quantity' => 3]);

            $subtotal = $this->cart->subtotal(); // $150
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
            $discount = $rule->getDiscount();

            // 10% of $150 = $15
            expect($discount->toFloat())->toBe(-15.00);
        });

        test('uses pattern matching for item IDs', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Buy 3+ Shirts Get 15% Off',
                minQuantity: 3,
                discount: -15.0,
                discountType: ConditionType::PERCENTAGE,
                itemIds: ['shirt-*']
            );

            $this->cart->addItem(['id' => 'shirt-blue', 'name' => 'Blue Shirt', 'price' => 40.00, 'quantity' => 2]);
            $this->cart->addItem(['id' => 'shirt-red', 'name' => 'Red Shirt', 'price' => 40.00, 'quantity' => 2]);
            $this->cart->addItem(['id' => 'pants-jeans', 'name' => 'Jeans', 'price' => 60.00, 'quantity' => 2]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeTrue();
            $discount = $rule->getDiscount();

            // 15% of $160 (4 shirts at $40 each) = $24
            expect($discount->toFloat())->toBe(-24.00);
        });
    });

    describe('Cart Integration', function (): void {
        test('cart can add a rule', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0
            );

            $this->cart->addRule($rule);

            expect($this->cart->rules())->toHaveCount(1);
            expect($this->cart->rules()->first()->getName())->toBe('Spend $100 Get 10% Off');
        });

        test('cart can remove a rule by name', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0
            );

            $this->cart->addRule($rule);
            $this->cart->removeRule('Spend $100 Get 10% Off');

            expect($this->cart->rules())->toHaveCount(0);
        });

        test('cart can clear all rules', function (): void {
            $this->cart->addRule(new ThresholdRule('Rule 1', 100, -10.0));
            $this->cart->addRule(new ThresholdRule('Rule 2', 200, -15.0));

            $this->cart->clearRules();

            expect($this->cart->rules())->toHaveCount(0);
        });

        test('rules are applied in cart total calculation', function (): void {
            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 50.00, 'quantity' => 4]);

            $this->cart->addRule(new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0
            ));

            $subtotal = $this->cart->subtotal(); // $200
            $total = $this->cart->total();

            // $200 - 10% = $180
            expect($subtotal->toFloat())->toBe(200.00);
            expect($total->toFloat())->toBe(180.00);
        });

        test('rules that do not apply are skipped', function (): void {
            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 20.00, 'quantity' => 2]);

            $this->cart->addRule(new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0
            ));

            $total = $this->cart->total();

            // $40 is below threshold, no discount applied
            expect($total->toFloat())->toBe(40.00);
        });

        test('multiple rules can be applied', function (): void {
            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 20.00, 'quantity' => 6]);

            $this->cart->addRule(new ThresholdRule(
                name: 'Spend $100 Get 5% Off',
                minSubtotal: 100.00,
                discount: -5.0
            ));

            $this->cart->addRule(new ItemQuantityRule(
                name: 'Buy 5+ Get 10% Off',
                minQuantity: 5,
                discount: -10.0
            ));

            $subtotal = $this->cart->subtotal(); // $120
            $total = $this->cart->total();

            // Threshold: -5% of $120 = -$6
            // ItemQuantity: -10% of $120 = -$12
            // Total: $120 - $6 - $12 = $102
            expect($total->toFloat())->toBe(102.00);
        });

        test('rules and conditions can be combined', function (): void {
            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 100.00, 'quantity' => 2]);

            // Add a cart condition
            $this->cart->addCondition(new \Daikazu\Flexicart\Conditions\Types\FixedCondition(
                name: 'Coupon',
                value: -10.0
            ));

            // Add a rule
            $this->cart->addRule(new ThresholdRule(
                name: 'Spend $150 Get 10% Off',
                minSubtotal: 150.00,
                discount: -10.0
            ));

            $subtotal = $this->cart->subtotal(); // $200
            $total = $this->cart->total();

            // Condition: -$10
            // Rule: -10% of $200 = -$20
            // Total: $200 - $10 - $20 = $170
            expect($total->toFloat())->toBe(170.00);
        });

        test('replacing a rule with same name updates it', function (): void {
            Event::fake();

            $rule1 = new ThresholdRule('Discount', 100, -10.0);
            $rule2 = new ThresholdRule('Discount', 100, -20.0);

            $this->cart->addRule($rule1);
            $this->cart->addRule($rule2);

            expect($this->cart->rules())->toHaveCount(1);

            // Check the replaced flag is true for the second add
            Event::assertDispatched(RuleAdded::class, function (RuleAdded $event) use ($rule2): bool {
                return $event->rule === $rule2 && $event->replaced === true;
            });
        });

        test('rules are persisted with cart', function (): void {
            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 100.00]);
            $this->cart->addRule(new ThresholdRule('Test Rule', 50.0, -10.0));

            // Create a new cart instance with same storage to verify persistence
            $newCart = new Cart($this->mockStorage);

            expect($newCart->rules())->toHaveCount(1);
            expect($newCart->rules()->first()->getName())->toBe('Test Rule');
        });

        test('reset clears rules', function (): void {
            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 100.00]);
            $this->cart->addRule(new ThresholdRule('Test Rule', 50.0, -10.0));

            $this->cart->reset();

            expect($this->cart->rules())->toHaveCount(0);
        });
    });

    describe('Rule Events', function (): void {
        test('RuleAdded event is dispatched when adding a rule', function (): void {
            Event::fake();

            $rule = new ThresholdRule('Test Rule', 100.0, -10.0);
            $this->cart->addRule($rule);

            Event::assertDispatched(RuleAdded::class, function (RuleAdded $event) use ($rule): bool {
                return $event->rule === $rule
                    && $event->cartId === $this->cart->id()
                    && $event->replaced === false;
            });
        });

        test('RuleRemoved event is dispatched when removing a rule', function (): void {
            $rule = new ThresholdRule('Test Rule', 100.0, -10.0);
            $this->cart->addRule($rule);

            Event::fake();

            $this->cart->removeRule('Test Rule');

            Event::assertDispatched(RuleRemoved::class, function (RuleRemoved $event) use ($rule): bool {
                return $event->rule === $rule
                    && $event->cartId === $this->cart->id();
            });
        });

        test('RulesCleared event is dispatched when clearing all rules', function (): void {
            $rule1 = new ThresholdRule('Rule 1', 100.0, -10.0);
            $rule2 = new ThresholdRule('Rule 2', 200.0, -15.0);
            $this->cart->addRule($rule1);
            $this->cart->addRule($rule2);

            Event::fake();

            $this->cart->clearRules();

            Event::assertDispatched(RulesCleared::class, function (RulesCleared $event): bool {
                return $event->cartId === $this->cart->id()
                    && $event->rules->count() === 2;
            });
        });

        test('events can be disabled via config', function (): void {
            config(['flexicart.events.enabled' => false]);

            Event::fake();

            $this->cart->addRule(new ThresholdRule('Test Rule', 100.0, -10.0));

            Event::assertNotDispatched(RuleAdded::class);
        });
    });

    describe('Rule Serialization', function (): void {
        test('rules can be converted to array', function (): void {
            $rule = new ThresholdRule(
                name: 'Spend $100 Get 10% Off',
                minSubtotal: 100.00,
                discount: -10.0,
                discountType: ConditionType::PERCENTAGE,
                attributes: ['promo_code' => 'SAVE10']
            );

            $array = $rule->toArray();

            expect($array['name'])->toBe('Spend $100 Get 10% Off');
            expect($array['value'])->toBe(-10.0);
            expect($array['type'])->toBe('percentage');
            expect($array['attributes']['promo_code'])->toBe('SAVE10');
        });

        test('rule getName returns correct name', function (): void {
            $rule = new BuyXGetYRule(
                name: 'Buy 2 Get 1 Free',
                buyQuantity: 2,
                getQuantity: 1
            );

            expect($rule->getName())->toBe('Buy 2 Get 1 Free');
        });
    });

    describe('Edge Cases', function (): void {
        test('BuyXGetY with empty cart returns zero discount', function (): void {
            $rule = new BuyXGetYRule('Buy 2 Get 1 Free', 2, 1);

            $subtotal = Price::zero();
            $rule->setCartContext(collect(), $subtotal);

            expect($rule->applies())->toBeFalse();
            expect($rule->getDiscount()->toFloat())->toBe(0.0);
        });

        test('ThresholdRule with zero subtotal does not apply', function (): void {
            $rule = new ThresholdRule('Spend $100', 100.0, -10.0);

            $rule->setCartContext(collect(), Price::zero());

            expect($rule->applies())->toBeFalse();
        });

        test('TieredRule with empty tiers returns zero', function (): void {
            $rule = new TieredRule('Volume Discount', []);

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 100.00]);
            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeFalse();
            expect($rule->getDiscount()->toFloat())->toBe(0.0);
        });

        test('ItemQuantityRule with no matching items returns zero', function (): void {
            $rule = new ItemQuantityRule(
                name: 'Bulk Discount',
                minQuantity: 5,
                discount: -10.0,
                itemIds: ['nonexistent']
            );

            $this->cart->addItem(['id' => 'widget', 'name' => 'Widget', 'price' => 20.00, 'quantity' => 10]);

            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            expect($rule->applies())->toBeFalse();
            expect($rule->getDiscount()->toFloat())->toBe(0.0);
        });

        test('rule calculate method delegates to getDiscount when context set', function (): void {
            $rule = new ThresholdRule('Test', 50.0, -10.0);

            $this->cart->addItem(['id' => 'product', 'name' => 'Product', 'price' => 100.00]);
            $subtotal = $this->cart->subtotal();
            $rule->setCartContext($this->cart->items(), $subtotal);

            $calculated = $rule->calculate($subtotal);

            expect($calculated->toFloat())->toBe(-10.00);
        });

        test('rule calculate returns zero without context', function (): void {
            $rule = new ThresholdRule('Test', 50.0, -10.0);

            // Don't set cart context
            $calculated = $rule->calculate(new Price(100.00));

            expect($calculated->toFloat())->toBe(0.0);
        });
    });
});
