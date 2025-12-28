<a href="https://mikewall.dev">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
  <img alt="Logo for Flexi Cart" src="art/header-light.png">
</picture>
</a>

# FlexiCart
[![PHP Version Require](https://img.shields.io/packagist/php-v/daikazu/flexicart?style=flat-square)](https://packagist.org/packages/daikazu/flexicart)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2B-red?style=flat-square&logo=laravel)](https://laravel.com)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/daikazu/flexicart.svg?style=flat-square)](https://packagist.org/packages/daikazu/flexicart)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/flexicart/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/daikazu/flexicart/actions?query=workflow%3Arun-tests+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/daikazu/flexicart/phpstan.yml?branch=main&label=PHPStan&=flat-square)](https://github.com/daikazu/flexicart/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/daikazu/flexicart.svg?style=flat-square)](https://packagist.org/packages/daikazu/flexicart)
[![GitHub forks](https://img.shields.io/github/forks/daikazu/flexicart?style=flat-square)](https://github.com/daikazu/flexicart/network)
[![GitHub stars](https://img.shields.io/github/stars/daikazu/flexicart?style=flat-square)](https://github.com/daikazu/flexicart/stargazers)

A flexible shopping cart package for Laravel with support for session or database storage, conditional pricing, cart merging, rules engine, and custom product attributes.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
  - [Adding Items](#adding-items-to-the-cart)
  - [Updating Items](#updating-items-in-the-cart)
  - [Removing Items](#removing-items-from-the-cart)
  - [Cart Totals](#getting-cart-content-and-calculations)
- [Conditions](#conditions)
  - [Understanding Conditions](#understanding-conditions)
  - [Adding Conditions](#adding-conditions)
  - [Removing Conditions](#removing-conditions)
  - [Non-Taxable Items](#marking-items-as-non-taxable)
- [Documentation](#documentation)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Credits](#credits)
- [License](#license)

## Features

- **Flexible Storage**: Use session storage (default) or database storage
- **Cart Item Conditions**: Apply discounts, fees, or any adjustments to items
    - Percentage-based adjustments (e.g., 10% discount)
    - Fixed-amount adjustments (e.g., $5 off, $2 add-on fee)
    - Stack multiple conditions on the same item
- **Rules Engine**: Advanced promotional rules with cart context access
    - Buy X Get Y deals
    - Threshold-based discounts
    - Tiered volume discounts
    - Quantity-based rules
- **Cart Merging**: Flexible strategies for merging carts (guest to user, wishlist to cart)
- **Event System**: Hook into cart lifecycle for analytics, inventory, and integrations
- **Custom Product Attributes**: Store any item-specific attributes (color, size, etc.)
- **Global Cart Conditions**: Apply conditions to the cart subtotal or only to taxable items
- **Precise Price Handling**: Uses Brick/Money for accurate currency calculations
- **Taxable Item Support**: Mark specific items as taxable or non-taxable
- **Easy Integration**: Simple API with Laravel Facade


## Installation

### Requirements

- PHP 8.3 or higher
- Laravel 11.0 or higher
- Brick/Money package (automatically installed)

### Install the Package

```bash
composer require daikazu/flexicart
```

### Publish Configuration

```bash
php artisan vendor:publish --tag="flexicart-config"
```

### Database Storage (Optional)

```bash
php artisan vendor:publish --tag="flexicart-migrations"
php artisan migrate
```

Then update your `.env` file:

```env
CART_STORAGE=database
```

## Basic Usage

### Adding Items to the Cart

```php
use Daikazu\Flexicart\Facades\Cart;

// Add item as array
Cart::addItem([
    'id' => 1,
    'name' => 'Product Name',
    'price' => 29.99,
    'quantity' => 2,
    'attributes' => [
        'color' => 'red',
        'size' => 'large'
    ]
]);

// Add multiple items at once
Cart::addItem([
    [
        'id' => 2,
        'name' => 'Another Product',
        'price' => 15.50,
        'quantity' => 1
    ],
    [
        'id' => 3,
        'name' => 'Third Product',
        'price' => 45.00,
        'quantity' => 3
    ]
]);
```

### Updating Items in the Cart

```php
// Update quantity
Cart::updateItem('item_id', ['quantity' => 5]);

// Update attributes
Cart::updateItem('item_id', [
    'attributes' => [
        'color' => 'blue',
        'size' => 'medium'
    ]
]);

// Update multiple properties
Cart::updateItem('item_id', [
    'quantity' => 3,
    'price' => 25.99,
    'attributes' => ['color' => 'green']
]);
```

### Removing Items from the Cart

```php
// Remove a specific item
Cart::removeItem('item_id');

// Clear all items from the cart
Cart::clear();

// Clear all items and conditions from cart
Cart::reset();
```

### Getting Cart Content and Calculations

```php
// Get all items
$items = Cart::items();

// Get a specific item
$item = Cart::item('item_id');

// Get cart counts
$totalItems = Cart::count(); // Total quantity of all items
$uniqueItems = Cart::uniqueCount(); // Number of unique items

// Check if cart is empty
$isEmpty = Cart::isEmpty();

// Get cart totals
$subtotal = Cart::subtotal(); // Subtotal before conditions
$total = Cart::total(); // Final total after all conditions
$taxableSubtotal = Cart::getTaxableSubtotal(); // Subtotal of taxable items only
```

## Conditions

### Understanding Conditions

Conditions are adjustments that can be applied to cart items or the entire cart:

- **Percentage Conditions**: Apply percentage-based adjustments (e.g., 10% discount)
- **Fixed Conditions**: Apply fixed-amount adjustments (e.g., $5 off)
- **Tax Conditions**: Special conditions for tax calculations

Conditions can target:
- **Individual Items**: Applied to specific cart items
- **Cart Subtotal**: Applied to the entire cart subtotal
- **Taxable Items**: Applied only to items marked as taxable

### Adding Conditions

```php
use Daikazu\Flexicart\Conditions\Types\PercentageCondition;
use Daikazu\Flexicart\Conditions\Types\FixedCondition;
use Daikazu\Flexicart\Enums\ConditionTarget;

// Add a 10% discount to the cart
$discount = new PercentageCondition(
    name: '10% Off Sale',
    value: -10, // Negative for discount
    target: ConditionTarget::SUBTOTAL
);
Cart::addCondition($discount);

// Add a $5 shipping fee
$shipping = new FixedCondition(
    name: 'Shipping Fee',
    value: 5.00,
    target: ConditionTarget::SUBTOTAL
);
Cart::addCondition($shipping);

// Add condition to a specific item
$itemDiscount = new PercentageCondition(
    name: 'Item Discount',
    value: -20,
    target: ConditionTarget::ITEM
);
Cart::addItemCondition('item_id', $itemDiscount);
```

### Removing Conditions

```php
// Remove a specific condition from the cart
Cart::removeCondition('10% Off Sale');

// Remove a condition from a specific item
Cart::removeItemCondition('item_id', 'Item Discount');

// Clear all cart conditions
Cart::clearConditions();
```

### Marking Items as Non-Taxable

```php
// Add non-taxable item
Cart::addItem([
    'id' => 4,
    'name' => 'Non-taxable Service',
    'price' => 100.00,
    'quantity' => 1,
    'taxable' => false
]);

// Update existing item to be non-taxable
Cart::updateItem('item_id', ['taxable' => false]);
```

## Documentation

For detailed documentation on specific features, see the following guides:

| Guide | Description |
|-------|-------------|
| [Configuration](docs/CONFIGURATION.md) | Storage options, currency settings, cleanup, and all config options |
| [Rules Engine](docs/RULES_ENGINE.md) | Advanced promotional rules: Buy X Get Y, thresholds, tiered discounts |
| [Cart Merging](docs/MERGING.md) | Merge strategies for guest-to-user carts, wishlists, and cart recovery |
| [Events](docs/EVENTS.md) | Cart lifecycle events for analytics, inventory, and integrations |
| [Working with Prices](docs/PRICES.md) | Price object API, arithmetic operations, and formatting |
| [Blade Templates](docs/BLADE.md) | Examples for displaying cart data in Blade views |
| [Extending FlexiCart](docs/EXTENDING.md) | Custom conditions, storage drivers, models, and merge strategies |

## Testing

```bash
composer test
```

## Troubleshooting

### Common Issues

**Cart data not persisting between requests**
- Ensure sessions are properly configured in your Laravel application
- If using database storage, verify migrations have been run
- Check that the `CART_STORAGE` environment variable is set correctly

**Price calculation errors**
- Verify that all price values are numeric
- Ensure currency codes are valid ISO codes

**Condition not applying correctly**
- Verify condition targets are set appropriately
- Check condition order values if multiple conditions exist
- Ensure condition values are properly signed (negative for discounts)

**Memory issues with large carts**
- Consider implementing cart item limits
- Don't use session storage due to cookie size limits
- Use database storage for better memory management
- Implement cart cleanup for old/abandoned carts

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Future Roadmap
- API endpoints

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mike Wall](https://github.com/daikazu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
