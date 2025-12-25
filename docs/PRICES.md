# Working with Prices

FlexiCart uses the [Brick/Money](https://github.com/brick/money) library for precise currency calculations, avoiding floating-point arithmetic errors common in financial applications.

## The Price Class

The `Price` class is an immutable value object that wraps Brick/Money, providing a clean API for price operations.

## Creating Price Objects

```php
use Daikazu\Flexicart\Price;

// Create from numeric value (uses configured currency)
$price = Price::from(29.99);

// Create with specific currency
$price = Price::from(29.99, 'EUR');

// Create from Money object
$money = \Brick\Money\Money::of(29.99, 'USD');
$price = new Price($money);

// Create zero price
$zero = Price::zero();
$zero = Price::zero('EUR'); // With specific currency
```

## Price Operations

All price operations return new `Price` instances (immutable pattern):

```php
$price1 = Price::from(10.00);
$price2 = Price::from(5.00);

// Addition
$total = $price1->plus($price2); // $15.00

// Subtraction
$difference = $price1->subtract($price2); // $5.00

// Multiplication
$doubled = $price1->multiplyBy(2); // $20.00

// Division
$half = $price1->divideBy(2); // $5.00
```

### Chaining Operations

```php
$finalPrice = Price::from(100.00)
    ->multiplyBy(0.9)  // 10% discount
    ->plus(Price::from(5.00));  // Add shipping
// Result: $95.00
```

## Formatting and Conversion

```php
$price = Price::from(1234.56);

// Get formatted string (locale-aware)
$formatted = $price->formatted(); // "$1,234.56"

// Get raw numeric value
$amount = $price->toFloat(); // 1234.56

// String representation (same as formatted)
$string = (string) $price; // "$1,234.56"

// Get minor value (cents/smallest unit)
$cents = $price->getMinorValue(); // 123456

// Get the underlying Brick/Money object
$money = $price->getMoney();
```

## Comparison

```php
$price1 = Price::from(10.00);
$price2 = Price::from(20.00);
$price3 = Price::from(10.00);

$price1->isLessThan($price2);      // true
$price1->isGreaterThan($price2);   // false
$price1->isEqualTo($price3);       // true
$price1->isZero();                 // false

// Check for negative
$discount = Price::from(-5.00);
$discount->isNegative();           // true
$discount->isPositive();           // false
```

## Working with Cart Totals

Cart methods return `Price` objects:

```php
use Daikazu\Flexicart\Facades\Cart;

// All return Price objects
$subtotal = Cart::subtotal();
$total = Cart::total();
$taxableSubtotal = Cart::getTaxableSubtotal();

// Format for display
echo "Subtotal: " . $subtotal->formatted();
echo "Total: " . $total->formatted();
```

## Item Price Methods

`CartItem` provides several price-related methods:

```php
$item = Cart::item('item-123');

// Unit price (single item)
$unitPrice = $item->unitPrice(); // Price object

// Subtotal (unit price * quantity)
$subtotal = $item->subtotal(); // Price object

// Total (subtotal + conditions applied)
$total = $item->total(); // Price object

// Get condition adjustments
$adjustments = $item->conditionsTotal(); // Price object
```

## Currency Handling

Prices respect the configured currency:

```php
// Set in config/flexicart.php
'currency' => 'USD',
'locale' => 'en_US',

// Or per-price
$euroPrice = Price::from(49.99, 'EUR');
$euroPrice->formatted(); // "49,99 EUR" (varies by locale)
```

## Best Practices

1. **Always use Price objects** - Never store or calculate with raw floats
2. **Use formatted() for display** - Ensures proper currency formatting
3. **Use toFloat() for APIs** - When you need a numeric value for external systems
4. **Compare with methods** - Use `isEqualTo()` instead of `==` for accuracy
5. **Handle currencies explicitly** - Be aware of currency mismatches in operations
