# Changelog

All notable changes to `flexicart` will be documented in this file.

## v1.3.0 - 2026-11-05

### Dependencies

- Added support for `brick/money` 0.12 through 0.15 (constraint is now `>=0.11 <1.0`). Laravel 13 projects resolve the newer versions, which no longer accept floats and removed `AbstractMoney::to()`. FlexiCart now casts floats to strings internally, so `Price::from(29.99)`, `multiplyBy(1.5)` and friends behave exactly as before on every supported version.
- `brick/math` is now a direct requirement (`>=0.14.2 <2.0`). The package uses `RoundingMode::HalfUp`, which older `brick/math` releases do not have.

### Testing

- Laravel 13 (Testbench 11) is now part of the CI matrix alongside Laravel 12, on both lowest and latest dependencies.

### Docs

- Explained the `taxable` flag on conditions (#3).
- The Prices guide now passes a string to `Money::of()`.

### Upgrade Note

Nothing changes if you only use FlexiCart's own API. If your code calls `brick/money` directly and you end up on 0.12 or newer, pass amounts as strings (`Money::of('29.99', 'USD')`, not `Money::of(29.99, 'USD')`) and use `toContext()` in place of `to()`.

**Full Changelog**: https://github.com/daikazu/flexicart/compare/v1.2.1...v1.3.0

## v1.2.1 - 2026-04-21

### Bug Fixes

- Fixed tax calculation to respect the `taxable` flag on conditions. Previously, conditions marked `taxable=false` (like non-taxable shipping) were still being included in the tax base, and conditions marked `taxable=true` were being double-counted. This also applied to item-level conditions. The `taxable` flag is now the single source of truth: only conditions with `taxable=true` contribute to the tax base, by their full adjustment amount. (#2)

### Behavior Change

If you previously relied on discounts reducing the tax base proportionally without explicitly marking them `taxable=true`, you'll need to set `taxable: true` on those conditions to preserve that behavior.

**Full Changelog**: https://github.com/daikazu/flexicart/compare/v1.2.0...v1.2.1

## v1.2.0 - 2026-03-17

Add Laravel 13 support

## v1.1.0 - 2025-12-28

### What's Changed

* Dev by @daikazu in https://github.com/daikazu/flexicart/pull/1

- Events
- Cart Merging
- Advanced Rules

### New Contributors

* @daikazu made their first contribution in https://github.com/daikazu/flexicart/pull/1

**Full Changelog**: https://github.com/daikazu/flexicart/compare/v1.0.0...v1.1.0

## v1.0.0 - 2025-07-08

### FlexiCart v1.0.0 - Initial Release

FlexiCart is a powerful, flexible shopping cart solution for Laravel applications with support for complex pricing scenarios and multiple storage options.

#### Key Features

##### **Flexible Storage Options**

- **Session Storage** (default) - Perfect for guest users and simple implementations
- **Database Storage** - Persistent carts with user association and cleanup management
- **Custom Storage** - Extensible interface for Redis, file system, or any custom backend

##### **Advanced Pricing & Conditions**

- **Item-Level Conditions** - Apply discounts, fees, or adjustments to specific cart items
  
- **Cart-Level Conditions** - Global conditions affecting subtotal or taxable items only
  
- **Multiple Condition Types**:
  
  - Percentage-based adjustments (e.g., 10% discount)
  - Fixed-amount adjustments (e.g., $5 shipping fee)
  - Stackable conditions with compound or parallel calculation modes
  
- **Precise Money Handling** - Uses Brick/Money library for accurate currency calculations
  

##### **Developer Experience**

- **Simple Facade API** - Clean, intuitive Laravel-style syntax
- **Comprehensive Tests** - Full test coverage with Pest framework
- **Extensible Architecture** - Easy to extend with custom conditions and storage
- **Laravel Integration** - Service provider, facade, and configuration publishing
