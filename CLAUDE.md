# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

FlexiCart is a Laravel shopping cart package with support for session or database storage, conditional pricing, cart merging, rules engine, and Brick/Money for precise currency calculations.

## Common Commands

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run a single test file
vendor/bin/pest tests/CartTest.php

# Run a specific test
vendor/bin/pest --filter="test name"

# Static analysis (PHPStan at max level)
composer analyse

# Format code (Pint)
composer format
# or
composer fix

# Lint with Duster
composer dust

# Fix with Duster
composer dust-fix

# Rector dry run
composer refactor

# Rector apply changes
composer refactor-fix
```

## Architecture

### Core Classes

- **`Cart`** (`src/Cart.php`) - Main cart class managing items, conditions, and rules. Uses `StorageInterface` for persistence. Dispatches events for cart lifecycle actions. Registered as a singleton bound to `CartInterface`.

- **`CartItem`** (`src/CartItem.php`) - Individual cart item with price, quantity, attributes (via `Fluent`), taxable flag, and item-level conditions. Constructed from arrays via `CartItem::make()`.

- **`Price`** (`src/Price.php`) - Immutable price value object wrapping `Brick\Money\Money`. All price arithmetic (plus, subtract, multiplyBy, divideBy, percentage) returns new `Price` instances. Handles currency formatting via locale. Subtotals/totals are never allowed to go negative.

### Conditions System

Two-tier condition system for price adjustments:

1. **Conditions** (`src/Conditions/`) - Simple adjustments applied to items or cart subtotal
   - Abstract base `Condition` class with `calculate(?Price $price): Price` contract
   - `PercentageCondition` / `FixedCondition` - Standard condition types (`src/Conditions/Types/`)
   - `PercentageTaxCondition` / `FixedTaxCondition` - Tax-specific conditions with preset targets
   - Target via `ConditionTarget` enum: `ITEM`, `SUBTOTAL`, `TAXABLE`
   - Type via `ConditionType` enum: `PERCENTAGE`, `FIXED`
   - Condition subclasses can set `$type` and `$target` as class-level defaults (checked via reflection in `Condition::make()`)

2. **Rules** (`src/Conditions/Rules/`) - Advanced conditions with cart context access
   - `RuleInterface` extends `ConditionInterface` - Rules receive full cart state via `setCartContext(Collection $items, Price $subtotal)`
   - `AbstractRule` provides base implementation with wildcard pattern matching for item IDs
   - Built-in rules: `BuyXGetYRule`, `ItemQuantityRule`, `ThresholdRule`, `TieredRule`
   - Rules are evaluated after conditions in `Cart::total()` and can inspect items, quantities, and subtotals

### Calculation Flow

`Cart::total()` applies adjustments in this order:
1. Item-level conditions (applied during `CartItem::subtotal()`)
2. Cart-level conditions sorted by target priority (SUBTOTAL first, then TAXABLE), then by taxable flag, order, and value
3. Rules (evaluated last with full cart context)

The `compound_discounts` config controls whether each adjustment uses the running total or original price as its base.

### Storage Layer

- `StorageInterface` (`src/Contracts/StorageInterface.php`) - Contract: `get()`, `put()`, `flush()`, `getCartId()`, `getCartById()`
- `SessionStorage` - Default, stores cart in Laravel session
- `DatabaseStorage` - Persists to `carts` and `cart_items` tables via `CartModel`/`CartItemModel`
- Custom storage: set `flexicart.storage_class` in config to a class implementing `StorageInterface`

### Service Provider & Bindings

`CartServiceProvider` (extends Spatie's `PackageServiceProvider`) registers:
- `StorageInterface` as singleton (resolves based on `flexicart.storage` config)
- `CartInterface` as singleton (the `Cart` class)
- `'cart'` alias for facade access
- Config, views, migration (`create_cart_table`), and `CleanupCartsCommand`

### Cart Merging

Merge strategies (`src/Strategies/`) for combining carts via `Cart::mergeFrom()`:
- `SumMergeStrategy` - Add quantities together (default)
- `ReplaceMergeStrategy` - Source replaces target
- `MaxMergeStrategy` - Keep highest quantity
- `KeepTargetMergeStrategy` - Only add new items
- `MergeStrategyFactory` resolves strategy by name string
- Implement `MergeStrategyInterface` for custom strategies

### Events

Events in `src/Events/` dispatch for all cart mutations when `flexicart.events.enabled` is true. All events extend `CartEvent` base class.

## Key Patterns

- All price arithmetic goes through the `Price` class which wraps `Brick\Money\Money`
- Conditions must extend `Condition` base class and implement `calculate(?Price $price): Price`
- Rules extend `AbstractRule` and implement `applies(): bool` and `getDiscount(): Price`
- Config in `config/flexicart.php` controls storage, currency, locale, compound discounts, merge behavior
- All classes use `declare(strict_types=1)` and PHP 8.3+ features
- PHPStan runs at max level with a baseline file

## Testing

Uses Pest with Laravel plugin. Tests in `tests/` directory with `TestCase` base class (extends Orchestra Testbench) and `RefreshDatabase` trait applied globally via `tests/Pest.php`. `MockStorage` in tests implements `StorageInterface` for unit testing without session/database.
