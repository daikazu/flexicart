# Changelog

All notable changes to `flexicart` will be documented in this file.

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
