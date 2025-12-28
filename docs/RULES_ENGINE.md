# Rules Engine

The Rules Engine extends FlexiCart's condition system to support complex promotional rules that have access to full cart context. Rules can make decisions based on items, quantities, and subtotals.

## Overview

Rules differ from standard conditions in that they:
- Have access to the full cart context (items and subtotal)
- Can apply conditional logic based on cart state
- Support pattern matching for item IDs (wildcards)
- Are automatically applied during `Cart::total()` calculation

## Available Rule Types

### BuyXGetYRule

Buy X items, get Y items free or at a discount. The discount is applied to the cheapest qualifying items.

```php
use Daikazu\Flexicart\Conditions\Rules\BuyXGetYRule;

// Buy 2 shirts, get 1 free
$cart->addRule(new BuyXGetYRule(
    name: 'Buy 2 Get 1 Free',
    buyQuantity: 2,
    getQuantity: 1,
    getDiscount: 100.0 // 100% off = free
));

// Buy 3, get 1 at 50% off
$cart->addRule(new BuyXGetYRule(
    name: 'Buy 3 Get 1 Half Off',
    buyQuantity: 3,
    getQuantity: 1,
    getDiscount: 50.0
));

// Apply only to specific items (supports wildcards)
$cart->addRule(new BuyXGetYRule(
    name: 'Buy 2 Get 1 Free Shirts',
    buyQuantity: 2,
    getQuantity: 1,
    getDiscount: 100.0,
    itemIds: ['shirt-*'] // Matches shirt-blue, shirt-red, etc.
));
```

**How it works:**
- Counts qualifying items in the cart
- Calculates how many complete "bundles" are available (buyQuantity + getQuantity)
- Applies discount to the cheapest items first
- Multiple bundles stack automatically

### ThresholdRule

Apply a discount when the cart subtotal exceeds a minimum amount.

```php
use Daikazu\Flexicart\Conditions\Rules\ThresholdRule;
use Daikazu\Flexicart\Enums\ConditionType;

// Spend $100, get 10% off
$cart->addRule(new ThresholdRule(
    name: 'Spend $100 Save 10%',
    minSubtotal: 100.00,
    discount: -10.0,
    discountType: ConditionType::PERCENTAGE
));

// Spend $200, get $25 off
$cart->addRule(new ThresholdRule(
    name: 'Spend $200 Save $25',
    minSubtotal: 200.00,
    discount: -25.0,
    discountType: ConditionType::FIXED
));
```

### TieredRule

Progressive discounts based on subtotal thresholds. Only the highest applicable tier is applied (not cumulative).

```php
use Daikazu\Flexicart\Conditions\Rules\TieredRule;

// Volume discount - higher spend = higher discount
$cart->addRule(new TieredRule(
    name: 'Volume Discount',
    tiers: [
        100 => 5,   // 5% off at $100+
        200 => 10,  // 10% off at $200+
        500 => 15,  // 15% off at $500+
    ]
));
```

**Getting tier information for display:**

```php
// Display all available tiers to the user
$rule = new TieredRule('Volume Discount', [100 => 5, 200 => 10, 500 => 15]);
$tiers = $rule->getTiers();

foreach ($tiers as $tier) {
    echo "Spend \${$tier['threshold']}+ and save {$tier['discount']}%";
}
```

### ItemQuantityRule

Apply a discount when the quantity of specific items meets a minimum.

```php
use Daikazu\Flexicart\Conditions\Rules\ItemQuantityRule;
use Daikazu\Flexicart\Enums\ConditionType;

// Buy 5+ widgets, get 10% off all widgets
$cart->addRule(new ItemQuantityRule(
    name: 'Bulk Widget Discount',
    minQuantity: 5,
    discount: -10.0,
    discountType: ConditionType::PERCENTAGE,
    itemIds: ['widget']
));

// Buy 10+ of any item, get $15 off
$cart->addRule(new ItemQuantityRule(
    name: 'Bulk Order Discount',
    minQuantity: 10,
    discount: -15.0,
    discountType: ConditionType::FIXED,
    itemIds: '*' // Applies to all items
));

// Buy 5+ items, get $2 off each item
$cart->addRule(new ItemQuantityRule(
    name: 'Per Item Discount',
    minQuantity: 5,
    discount: -2.0,
    discountType: ConditionType::FIXED,
    itemIds: '*',
    perItem: true // Discount applies per qualifying item
));
```

## Pattern Matching for Item IDs

Rules that accept `itemIds` support wildcard pattern matching:

```php
// Match all items
$itemIds = '*'

// Match a specific item
$itemIds = 'widget-123'

// Match items starting with 'shirt-'
$itemIds = 'shirt-*'

// Match items ending with '-sale'
$itemIds = '*-sale'

// Match multiple patterns
$itemIds = ['shirt-*', 'pants-*', 'hat-special']
```

## Managing Rules

### Adding Rules

```php
$cart->addRule(new ThresholdRule('Holiday Sale', 50.0, -15.0));
```

If you add a rule with the same name as an existing rule, it will be replaced.

### Getting All Rules

```php
$rules = $cart->rules(); // Returns Collection<string, RuleInterface>
```

### Removing Rules

```php
// Remove a specific rule by name
$cart->removeRule('Holiday Sale');

// Clear all rules
$cart->clearRules();
```

### Rule Persistence

Rules are automatically persisted with the cart. When you reload a cart, all rules are restored.

## Combining Rules and Conditions

Rules work alongside standard conditions. Both are applied during `Cart::total()` calculation:

```php
// Standard condition - fixed coupon discount
$cart->addCondition(new FixedCondition(
    name: 'SAVE10',
    value: -10.00
));

// Rule - threshold discount
$cart->addRule(new ThresholdRule(
    name: 'Spend More Save More',
    minSubtotal: 100.00,
    discount: -10.0
));

// Both discounts apply if conditions are met
$total = $cart->total();
```

## Rule Events

Events are dispatched when rules are modified:

| Event | Trigger |
|-------|---------|
| `RuleAdded` | When a rule is added or replaced |
| `RuleRemoved` | When a rule is removed |
| `RulesCleared` | When all rules are cleared |

```php
use Daikazu\Flexicart\Events\RuleAdded;

Event::listen(RuleAdded::class, function (RuleAdded $event) {
    Log::info("Rule '{$event->rule->getName()}' added to cart {$event->cartId}");

    if ($event->replaced) {
        Log::info("This rule replaced an existing rule with the same name");
    }
});
```

## Creating Custom Rules

To create a custom rule, extend `AbstractRule` and implement the required methods:

```php
use Daikazu\Flexicart\Conditions\Rules\AbstractRule;
use Daikazu\Flexicart\Price;

class FirstTimeCustomerRule extends AbstractRule
{
    public function __construct(
        string $name,
        public readonly float $discountPercent,
        array|Fluent $attributes = [],
        int $order = 0,
        bool $taxable = false
    ) {
        parent::__construct($name, $discountPercent, $attributes, $order, $taxable);
        $this->type = ConditionType::PERCENTAGE;
    }

    public function applies(): bool
    {
        // Check if this is a first-time customer via attributes
        return $this->attributes->get('is_first_order', false) === true;
    }

    public function getDiscount(): Price
    {
        $discountAmount = $this->subtotal->multiplyBy($this->discountPercent / 100);

        return new Price(-$discountAmount->toFloat());
    }
}

// Usage
$cart->addRule(new FirstTimeCustomerRule(
    name: 'First Order Discount',
    discountPercent: 20.0,
    attributes: ['is_first_order' => $user->orders()->count() === 0]
));
```

### Available Helper Methods in AbstractRule

When creating custom rules, you have access to these protected helper methods:

```php
// Get total quantity of all items in cart
$totalQty = $this->getTotalQuantity();

// Get quantity of items matching specific IDs (supports wildcards)
$shirtQty = $this->getItemQuantity(['shirt-*']);

// Get collection of items matching specific IDs
$shirts = $this->getMatchingItems(['shirt-*']);

// Check if a value matches a pattern
$matches = $this->matchesPattern('shirt-blue', 'shirt-*'); // true
```

### Available Context in Rules

Rules have access to:

```php
// Collection of all cart items
$this->items; // Collection<string, CartItem>

// Cart subtotal
$this->subtotal; // Price

// Check if context has been set
$this->contextSet; // bool
```

## Best Practices

1. **Use descriptive rule names** - They're used as unique identifiers
2. **Order rules appropriately** - Use the `order` parameter if rule execution order matters
3. **Consider combining with conditions** - Use rules for cart-context-aware logic, conditions for simple discounts
4. **Test edge cases** - Empty carts, exactly-at-threshold amounts, multiple bundles
5. **Use pattern matching wisely** - Wildcards are powerful but can be too broad
