# FlexiCart Commerce Client Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add an optional CommerceClient to the flexicart package that consumes the flexi-commerce REST API, enabling APP-B to browse products, resolve pricing, and add configured items to the local cart.

**Architecture:** The client lives in `src/Commerce/` as an opt-in feature gated by `flexicart.commerce.enabled` config. It uses Laravel's HTTP client to call the flexi-commerce API, returns typed DTOs for catalog data, and provides an `addToCart()` method that maps API responses directly into `Cart::addItem()` with proper `Condition` objects. GET endpoints are cached with configurable TTL; POST endpoints are never cached.

**Tech Stack:** PHP 8.3+, Laravel HTTP Client, Brick\Money (shared dep), Pest 4, Orchestra Testbench

**Design Spec:** See `~/Code/flexi-commerce/docs/plans/2026-02-20-api-design.md` (FlexiCart Client section, lines 166-213)

**Working Directory:** `/Users/mikewall/Code/flexicart`

---

## Context for Implementer

### Package conventions
- Namespace: `Daikazu\Flexicart`
- All source files use `declare(strict_types=1)`
- Exceptions are `final class extends Exception`
- Service provider: `src/CartServiceProvider.php` (Spatie `PackageServiceProvider`)
- Config: `config/flexicart.php`
- Tests: Pest 4 with `describe()` blocks, `uses(TestCase::class, RefreshDatabase::class)` in `Pest.php`

### Key existing classes
- `Cart` implements `CartInterface` — `addItem(array|CartItem)`, `item(string $id): ?CartItem`
- `CartItem` — constructor accepts `{id, name, price, quantity, attributes, conditions, taxable}`
- `Price` — immutable value object wrapping `Brick\Money\Money`, accepts `int|float|string|Money`
- `Condition::fromArray(array $data)` — reconstructs typed condition from `{name, value, type, target, attributes, order, taxable}`
- `ConditionTarget` enum: `ITEM`, `SUBTOTAL`, `TAXABLE`
- `ConditionType` enum: `FIXED`, `PERCENTAGE`

### API endpoints the client will call (served by flexi-commerce)
- `GET /products` — paginated list (keys: `slug`, `name`, `description`, `status`, `type`, `meta`, `prices`)
- `GET /products/{slug}` — full product (adds: `price_tiers`, `options`, `variants`, `addon_groups`)
- `GET /collections` — paginated list (keys: `slug`, `name`, `type`, `description`, `is_active`, `parent_id`, `meta`, `products`, `children`)
- `GET /collections/{slug}` — collection with products
- `POST /products/{slug}/resolve-price` — body: `{variant_id, quantity, currency, addon_selections}` → `{product_slug, variant, quantity, currency, unit_price, tier_applied, addons, line_total}`
- `POST /products/{slug}/cart-item` — same body → `{id, name, price, quantity, attributes, conditions}`

All endpoints require `Authorization: Bearer {token}` header. Responses are wrapped in `{"data": ...}` with standard Laravel pagination meta.

---

## Task 1: Config Additions

**Files:**
- Modify: `config/flexicart.php`
- Test: `tests/Commerce/CommerceConfigTest.php`

**Step 1: Write the failing test**

Create `tests/Commerce/CommerceConfigTest.php`:

```php
<?php

declare(strict_types=1);

describe('Commerce Config', function (): void {
    test('commerce config has required keys', function (): void {
        $config = config('flexicart.commerce');

        expect($config)->toBeArray()
            ->and($config)->toHaveKeys([
                'enabled',
                'base_url',
                'token',
                'timeout',
                'cache',
            ])
            ->and($config['cache'])->toHaveKeys(['enabled', 'ttl']);
    });

    test('commerce is disabled by default', function (): void {
        expect(config('flexicart.commerce.enabled'))->toBeFalse();
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/CommerceConfigTest.php`
Expected: FAIL — `flexicart.commerce` returns null

**Step 3: Add config block**

Add to end of `config/flexicart.php` (before the closing `];`):

```php
    /*
    |--------------------------------------------------------------------------
    | Remote Commerce
    |--------------------------------------------------------------------------
    |
    | Connect to a flexi-commerce API server to browse products and resolve
    | pricing remotely. Enable this when APP-B needs catalog data from APP-A.
    |
    */
    'commerce' => [
        'enabled'  => env('FLEXI_COMMERCE_ENABLED', false),
        'base_url' => env('FLEXI_COMMERCE_URL'),
        'token'    => env('FLEXI_COMMERCE_TOKEN'),
        'timeout'  => env('FLEXI_COMMERCE_TIMEOUT', 10),
        'cache'    => [
            'enabled' => env('FLEXI_COMMERCE_CACHE', true),
            'ttl'     => env('FLEXI_COMMERCE_CACHE_TTL', 300),
        ],
    ],
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Commerce/CommerceConfigTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add config/flexicart.php tests/Commerce/CommerceConfigTest.php
git commit -m "feat: add commerce client config block"
```

---

## Task 2: Exception Classes

**Files:**
- Create: `src/Commerce/Exceptions/CommerceConnectionException.php`
- Create: `src/Commerce/Exceptions/CommerceAuthenticationException.php`
- Test: `tests/Commerce/CommerceExceptionsTest.php`

**Step 1: Write the failing test**

Create `tests/Commerce/CommerceExceptionsTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\Exceptions\CommerceAuthenticationException;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;

describe('Commerce Exceptions', function (): void {
    test('CommerceConnectionException is throwable with message', function (): void {
        $e = new CommerceConnectionException('Connection refused');
        expect($e)->toBeInstanceOf(\Exception::class)
            ->and($e->getMessage())->toBe('Connection refused');
    });

    test('CommerceAuthenticationException is throwable with message', function (): void {
        $e = new CommerceAuthenticationException('Invalid token');
        expect($e)->toBeInstanceOf(\Exception::class)
            ->and($e->getMessage())->toBe('Invalid token');
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/CommerceExceptionsTest.php`
Expected: FAIL — classes not found

**Step 3: Create exception classes**

Create `src/Commerce/Exceptions/CommerceConnectionException.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\Exceptions;

use Exception;

final class CommerceConnectionException extends Exception {}
```

Create `src/Commerce/Exceptions/CommerceAuthenticationException.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\Exceptions;

use Exception;

final class CommerceAuthenticationException extends Exception {}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Commerce/CommerceExceptionsTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Commerce/Exceptions/ tests/Commerce/CommerceExceptionsTest.php
git commit -m "feat: add commerce exception classes"
```

---

## Task 3: DTOs

**Files:**
- Create: `src/Commerce/DTOs/ProductData.php`
- Create: `src/Commerce/DTOs/CollectionData.php`
- Create: `src/Commerce/DTOs/PriceBreakdownData.php`
- Create: `src/Commerce/DTOs/CartItemData.php`
- Test: `tests/Commerce/DTOs/ProductDataTest.php`
- Test: `tests/Commerce/DTOs/CollectionDataTest.php`
- Test: `tests/Commerce/DTOs/PriceBreakdownDataTest.php`
- Test: `tests/Commerce/DTOs/CartItemDataTest.php`

### Step 1: Write the failing tests

Create `tests/Commerce/DTOs/ProductDataTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\ProductData;

describe('ProductData', function (): void {
    test('creates from API array with all fields', function (): void {
        $data = ProductData::fromArray([
            'slug' => 'custom-patches',
            'name' => 'Custom Patches',
            'type' => 'configurable',
            'description' => 'High quality patches',
            'meta' => ['key' => 'value'],
            'prices' => [['key' => 'retail', 'currency' => 'USD', 'amount_minor' => 602]],
            'options' => [['code' => 'size', 'name' => 'Size']],
            'variants' => [['id' => 1, 'sku' => 'P-001']],
            'addon_groups' => [['code' => 'backing']],
        ]);

        expect($data->slug)->toBe('custom-patches')
            ->and($data->name)->toBe('Custom Patches')
            ->and($data->type)->toBe('configurable')
            ->and($data->description)->toBe('High quality patches')
            ->and($data->prices)->toHaveCount(1)
            ->and($data->options)->toHaveCount(1)
            ->and($data->variants)->toHaveCount(1)
            ->and($data->addonGroups)->toHaveCount(1)
            ->and($data->raw)->toBeArray();
    });

    test('handles missing optional fields gracefully', function (): void {
        $data = ProductData::fromArray([
            'slug' => 'simple',
            'name' => 'Simple Product',
            'type' => 'simple',
        ]);

        expect($data->description)->toBeNull()
            ->and($data->prices)->toBe([])
            ->and($data->options)->toBe([])
            ->and($data->variants)->toBe([])
            ->and($data->addonGroups)->toBe([]);
    });
});
```

Create `tests/Commerce/DTOs/CollectionDataTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\CollectionData;

describe('CollectionData', function (): void {
    test('creates from API array', function (): void {
        $data = CollectionData::fromArray([
            'slug' => 'patches',
            'name' => 'Patches',
            'type' => 'category',
            'description' => 'All patches',
            'is_active' => true,
            'parent_id' => null,
            'children' => [],
            'products' => [['slug' => 'p1']],
        ]);

        expect($data->slug)->toBe('patches')
            ->and($data->name)->toBe('Patches')
            ->and($data->type)->toBe('category')
            ->and($data->isActive)->toBeTrue()
            ->and($data->products)->toHaveCount(1);
    });
});
```

Create `tests/Commerce/DTOs/PriceBreakdownDataTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData;

describe('PriceBreakdownData', function (): void {
    test('creates from API array', function (): void {
        $data = PriceBreakdownData::fromArray([
            'product_slug' => 'custom-patches',
            'variant' => ['id' => 42, 'sku' => 'P-2-00', 'name' => '2 Inch'],
            'quantity' => 25,
            'currency' => 'USD',
            'unit_price' => '6.02',
            'tier_applied' => ['min_qty' => 20, 'max_qty' => 49],
            'addons' => [
                ['group_code' => 'backing', 'addon_code' => 'iron-on', 'name' => 'Iron-On', 'unit_amount' => '0.12', 'line_amount' => '3.00', 'is_free' => false],
            ],
            'line_total' => '153.50',
        ]);

        expect($data->productSlug)->toBe('custom-patches')
            ->and($data->quantity)->toBe(25)
            ->and($data->currency)->toBe('USD')
            ->and($data->unitPrice)->toBe('6.02')
            ->and($data->lineTotal)->toBe('153.50')
            ->and($data->variant)->not->toBeNull()
            ->and($data->tierApplied)->not->toBeNull()
            ->and($data->addons)->toHaveCount(1);
    });

    test('handles null variant and tier', function (): void {
        $data = PriceBreakdownData::fromArray([
            'product_slug' => 'simple',
            'variant' => null,
            'quantity' => 1,
            'currency' => 'USD',
            'unit_price' => '5.00',
            'tier_applied' => null,
            'addons' => [],
            'line_total' => '5.00',
        ]);

        expect($data->variant)->toBeNull()
            ->and($data->tierApplied)->toBeNull()
            ->and($data->addons)->toBe([]);
    });
});
```

Create `tests/Commerce/DTOs/CartItemDataTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\CartItemData;
use Daikazu\Flexicart\Conditions\Contracts\ConditionInterface;

describe('CartItemData', function (): void {
    test('creates from API array', function (): void {
        $data = CartItemData::fromArray([
            'id' => 'P-2-00:backing=iron-on',
            'name' => 'Custom Patches - 2 Inch',
            'price' => 6.02,
            'quantity' => 25,
            'attributes' => [
                'product_slug' => 'custom-patches',
                'variant_id' => 42,
                'sku' => 'P-2-00',
                'option_values' => ['size' => '2 Inch'],
                'source' => 'flexi-commerce',
            ],
            'conditions' => [
                [
                    'name' => 'Addon: Iron-On Backing',
                    'value' => 0.12,
                    'type' => 'fixed',
                    'target' => 'item',
                    'attributes' => ['addon_code' => 'iron-on', 'group_code' => 'backing', 'modifier_id' => 7],
                    'order' => 0,
                    'taxable' => true,
                ],
            ],
        ]);

        expect($data->id)->toBe('P-2-00:backing=iron-on')
            ->and($data->name)->toBe('Custom Patches - 2 Inch')
            ->and($data->price)->toBe(6.02)
            ->and($data->quantity)->toBe(25)
            ->and($data->attributes)->toBeArray()
            ->and($data->conditions)->toHaveCount(1);
    });

    test('toCartArray converts conditions to ConditionInterface instances', function (): void {
        $data = CartItemData::fromArray([
            'id' => 'P-001',
            'name' => 'Product',
            'price' => 10.00,
            'quantity' => 1,
            'attributes' => [],
            'conditions' => [
                [
                    'name' => 'Addon: Test',
                    'value' => 1.50,
                    'type' => 'fixed',
                    'target' => 'item',
                    'attributes' => [],
                    'order' => 0,
                    'taxable' => true,
                ],
            ],
        ]);

        $arr = $data->toCartArray();

        expect($arr)->toHaveKeys(['id', 'name', 'price', 'quantity', 'attributes', 'conditions'])
            ->and($arr['id'])->toBe('P-001')
            ->and($arr['price'])->toBe(10.00)
            ->and($arr['conditions'])->toHaveCount(1)
            ->and($arr['conditions'][0])->toBeInstanceOf(ConditionInterface::class);
    });

    test('toCartArray with empty conditions returns empty array', function (): void {
        $data = CartItemData::fromArray([
            'id' => 'P-001',
            'name' => 'Product',
            'price' => 10.00,
            'quantity' => 1,
            'attributes' => [],
            'conditions' => [],
        ]);

        expect($data->toCartArray()['conditions'])->toBe([]);
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Commerce/DTOs/`
Expected: FAIL — classes not found

### Step 3: Create DTO classes

Create `src/Commerce/DTOs/ProductData.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

final readonly class ProductData
{
    /**
     * @param  array<int, array<string, mixed>>  $prices
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<int, array<string, mixed>>  $addonGroups
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $type,
        public ?string $description,
        public array $prices,
        public array $options,
        public array $variants,
        public array $addonGroups,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            type: $data['type'],
            description: $data['description'] ?? null,
            prices: $data['prices'] ?? [],
            options: $data['options'] ?? [],
            variants: $data['variants'] ?? [],
            addonGroups: $data['addon_groups'] ?? [],
            raw: $data,
        );
    }
}
```

Create `src/Commerce/DTOs/CollectionData.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

final readonly class CollectionData
{
    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $type,
        public ?string $description,
        public bool $isActive,
        public ?int $parentId,
        public array $children,
        public array $products,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            type: $data['type'] ?? null,
            description: $data['description'] ?? null,
            isActive: $data['is_active'] ?? true,
            parentId: $data['parent_id'] ?? null,
            children: $data['children'] ?? [],
            products: $data['products'] ?? [],
            raw: $data,
        );
    }
}
```

Create `src/Commerce/DTOs/PriceBreakdownData.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

final readonly class PriceBreakdownData
{
    /**
     * @param  array{id: int, sku: string, name: string}|null  $variant
     * @param  array{min_qty: int, max_qty: int|null}|null  $tierApplied
     * @param  array<int, array<string, mixed>>  $addons
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $productSlug,
        public ?array $variant,
        public int $quantity,
        public string $currency,
        public string $unitPrice,
        public ?array $tierApplied,
        public array $addons,
        public string $lineTotal,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            productSlug: $data['product_slug'],
            variant: $data['variant'],
            quantity: $data['quantity'],
            currency: $data['currency'],
            unitPrice: $data['unit_price'],
            tierApplied: $data['tier_applied'],
            addons: $data['addons'],
            lineTotal: $data['line_total'],
            raw: $data,
        );
    }
}
```

Create `src/Commerce/DTOs/CartItemData.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

use Daikazu\Flexicart\Conditions\Condition;

final readonly class CartItemData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public float $price,
        public int $quantity,
        public array $attributes,
        public array $conditions,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            price: (float) $data['price'],
            quantity: (int) $data['quantity'],
            attributes: $data['attributes'] ?? [],
            conditions: $data['conditions'] ?? [],
            raw: $data,
        );
    }

    /**
     * Convert to an array compatible with Cart::addItem().
     *
     * @return array<string, mixed>
     */
    public function toCartArray(): array
    {
        $conditions = array_map(
            fn (array $c) => Condition::fromArray($c),
            $this->conditions,
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'attributes' => $this->attributes,
            'conditions' => $conditions,
        ];
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Commerce/DTOs/`
Expected: PASS (all DTO tests)

**Step 5: Commit**

```bash
git add src/Commerce/DTOs/ tests/Commerce/DTOs/
git commit -m "feat: add commerce DTOs for API response mapping"
```

---

## Task 4: CommerceClient — Read Endpoints

**Files:**
- Create: `src/Commerce/CommerceClient.php`
- Test: `tests/Commerce/CommerceClientReadTest.php`

**Reference:** The flexi-commerce API returns standard Laravel paginated JSON:
```json
{
  "data": [...],
  "links": {"first": "...", "last": "...", "prev": null, "next": "..."},
  "meta": {"current_page": 1, "last_page": 5, "per_page": 15, "total": 72}
}
```

### Step 1: Write the failing tests

Create `tests/Commerce/CommerceClientReadTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Commerce\DTOs\CollectionData;
use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceAuthenticationException;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

function makeClient(): CommerceClient
{
    return new CommerceClient(
        baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
        token: 'test-token',
        timeout: 5,
        cacheEnabled: false,
        cacheTtl: 300,
    );
}

describe('CommerceClient Read Endpoints', function (): void {

    test('products() returns paginated ProductData', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [
                    ['slug' => 'product-a', 'name' => 'Product A', 'type' => 'simple'],
                    ['slug' => 'product-b', 'name' => 'Product B', 'type' => 'configurable'],
                ],
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 2, 'path' => ''],
            ]),
        ]);

        $result = makeClient()->products();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->total())->toBe(2)
            ->and($result->items()[0])->toBeInstanceOf(ProductData::class)
            ->and($result->items()[0]->slug)->toBe('product-a');
    });

    test('products() passes filter query parameters', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [],
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        makeClient()->products(['page' => 2, 'per_page' => 5]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=2')
            && str_contains($request->url(), 'per_page=5'));
    });

    test('product() returns a single ProductData', function (): void {
        Http::fake([
            '*/products/custom-patches' => Http::response([
                'data' => [
                    'slug' => 'custom-patches',
                    'name' => 'Custom Patches',
                    'type' => 'configurable',
                    'options' => [['code' => 'size', 'name' => 'Size']],
                    'variants' => [['id' => 1, 'sku' => 'P-001']],
                    'addon_groups' => [],
                ],
            ]),
        ]);

        $result = makeClient()->product('custom-patches');

        expect($result)->toBeInstanceOf(ProductData::class)
            ->and($result->slug)->toBe('custom-patches')
            ->and($result->options)->toHaveCount(1)
            ->and($result->variants)->toHaveCount(1);
    });

    test('collections() returns paginated CollectionData', function (): void {
        Http::fake([
            '*/collections*' => Http::response([
                'data' => [
                    ['slug' => 'patches', 'name' => 'Patches', 'type' => 'category', 'is_active' => true],
                ],
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'path' => ''],
            ]),
        ]);

        $result = makeClient()->collections();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->total())->toBe(1)
            ->and($result->items()[0])->toBeInstanceOf(CollectionData::class)
            ->and($result->items()[0]->slug)->toBe('patches');
    });

    test('collection() returns a single CollectionData', function (): void {
        Http::fake([
            '*/collections/patches' => Http::response([
                'data' => [
                    'slug' => 'patches',
                    'name' => 'Patches',
                    'type' => 'category',
                    'is_active' => true,
                    'products' => [['slug' => 'custom-patches', 'name' => 'Custom Patches', 'type' => 'configurable']],
                ],
            ]),
        ]);

        $result = makeClient()->collection('patches');

        expect($result)->toBeInstanceOf(CollectionData::class)
            ->and($result->slug)->toBe('patches')
            ->and($result->products)->toHaveCount(1);
    });

    test('sends bearer token in Authorization header', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [],
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        makeClient()->products();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/CommerceClientReadTest.php`
Expected: FAIL — CommerceClient class not found

### Step 3: Create CommerceClient with read endpoints

Create `src/Commerce/CommerceClient.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Commerce\DTOs\CartItemData;
use Daikazu\Flexicart\Commerce\DTOs\CollectionData;
use Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData;
use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceAuthenticationException;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;
use Daikazu\Flexicart\Contracts\CartInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class CommerceClient
{
    private PendingRequest $http;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout = 10,
        private readonly bool $cacheEnabled = true,
        private readonly int $cacheTtl = 300,
    ) {
        $this->http = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->token)
            ->timeout($this->timeout)
            ->acceptJson();
    }

    /**
     * List active products (paginated).
     *
     * @param  array<string, mixed>  $filters  Query parameters (page, per_page, etc.)
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function products(array $filters = []): LengthAwarePaginator
    {
        $response = $this->get('/products', $filters);

        return $this->toPaginator($response, fn (array $item) => ProductData::fromArray($item));
    }

    /**
     * Get a single product by slug.
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function product(string $slug): ProductData
    {
        $response = $this->get("/products/{$slug}");

        return ProductData::fromArray($response['data']);
    }

    /**
     * List active collections (paginated).
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function collections(array $filters = []): LengthAwarePaginator
    {
        $response = $this->get('/collections', $filters);

        return $this->toPaginator($response, fn (array $item) => CollectionData::fromArray($item));
    }

    /**
     * Get a single collection by slug.
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function collection(string $slug): CollectionData
    {
        $response = $this->get("/collections/{$slug}");

        return CollectionData::fromArray($response['data']);
    }

    /**
     * Perform a GET request, with optional caching.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    private function get(string $path, array $query = []): array
    {
        $fetcher = fn (): array => $this->request('get', $path, $query);

        if (! $this->cacheEnabled) {
            return $fetcher();
        }

        $cacheKey = 'flexicart:commerce:'.md5($path.serialize($query));

        return Cache::remember($cacheKey, $this->cacheTtl, $fetcher);
    }

    /**
     * Perform an HTTP request and handle errors.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    private function request(string $method, string $path, array $data = []): array
    {
        try {
            /** @var Response $response */
            $response = $method === 'get'
                ? $this->http->get($path, $data)
                : $this->http->post($path, $data);

            if ($response->status() === 401) {
                throw new CommerceAuthenticationException(
                    $response->json('error.message', 'Authentication failed.')
                );
            }

            if ($response->failed()) {
                throw new CommerceConnectionException(
                    $response->json('error.message', "Request failed with status {$response->status()}."),
                    $response->status(),
                );
            }

            return $response->json();
        } catch (ConnectionException $e) {
            throw new CommerceConnectionException(
                "Could not connect to commerce API: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Convert a paginated API response into a LengthAwarePaginator.
     *
     * @param  array<string, mixed>  $response
     * @param  callable(array<string, mixed>): mixed  $mapper
     */
    private function toPaginator(array $response, callable $mapper): LengthAwarePaginator
    {
        $items = array_map($mapper, $response['data']);
        $meta = $response['meta'];

        return new LengthAwarePaginator(
            items: $items,
            total: $meta['total'],
            perPage: $meta['per_page'],
            currentPage: $meta['current_page'],
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Commerce/CommerceClientReadTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Commerce/CommerceClient.php tests/Commerce/CommerceClientReadTest.php
git commit -m "feat: add CommerceClient with read endpoints"
```

---

## Task 5: CommerceClient — Action Endpoints (resolvePrice, cartItem, addToCart)

**Files:**
- Modify: `src/Commerce/CommerceClient.php`
- Test: `tests/Commerce/CommerceClientActionTest.php`

### Step 1: Write the failing tests

Create `tests/Commerce/CommerceClientActionTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Cart;
use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Commerce\DTOs\CartItemData;
use Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData;
use Daikazu\Flexicart\Contracts\CartInterface;
use Daikazu\Flexicart\Tests\MockStorage;
use Illuminate\Support\Facades\Http;

function makeActionClient(): CommerceClient
{
    return new CommerceClient(
        baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
        token: 'test-token',
        timeout: 5,
        cacheEnabled: false,
        cacheTtl: 300,
    );
}

describe('CommerceClient Action Endpoints', function (): void {

    test('resolvePrice() returns PriceBreakdownData', function (): void {
        Http::fake([
            '*/products/patches/resolve-price' => Http::response([
                'data' => [
                    'product_slug' => 'patches',
                    'variant' => ['id' => 42, 'sku' => 'P-2-00', 'name' => '2 Inch'],
                    'quantity' => 10,
                    'currency' => 'USD',
                    'unit_price' => '6.02',
                    'tier_applied' => null,
                    'addons' => [],
                    'line_total' => '60.20',
                ],
            ]),
        ]);

        $result = makeActionClient()->resolvePrice('patches', [
            'variant_id' => 42,
            'quantity' => 10,
            'currency' => 'USD',
        ]);

        expect($result)->toBeInstanceOf(PriceBreakdownData::class)
            ->and($result->unitPrice)->toBe('6.02')
            ->and($result->lineTotal)->toBe('60.20');
    });

    test('resolvePrice() sends correct POST body', function (): void {
        Http::fake([
            '*/products/patches/resolve-price' => Http::response([
                'data' => [
                    'product_slug' => 'patches',
                    'variant' => null,
                    'quantity' => 1,
                    'currency' => 'USD',
                    'unit_price' => '5.00',
                    'tier_applied' => null,
                    'addons' => [],
                    'line_total' => '5.00',
                ],
            ]),
        ]);

        makeActionClient()->resolvePrice('patches', [
            'variant_id' => 42,
            'quantity' => 10,
            'currency' => 'USD',
            'addon_selections' => ['backing' => ['iron-on' => 1]],
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $body['variant_id'] === 42
                && $body['quantity'] === 10
                && $body['currency'] === 'USD'
                && $body['addon_selections']['backing']['iron-on'] === 1;
        });
    });

    test('cartItem() returns CartItemData', function (): void {
        Http::fake([
            '*/products/patches/cart-item' => Http::response([
                'data' => [
                    'id' => 'P-2-00:backing=iron-on',
                    'name' => 'Custom Patches - 2 Inch',
                    'price' => 6.02,
                    'quantity' => 10,
                    'attributes' => ['product_slug' => 'patches', 'sku' => 'P-2-00'],
                    'conditions' => [
                        [
                            'name' => 'Addon: Iron-On',
                            'value' => 0.12,
                            'type' => 'fixed',
                            'target' => 'item',
                            'attributes' => ['addon_code' => 'iron-on'],
                            'order' => 0,
                            'taxable' => true,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = makeActionClient()->cartItem('patches', [
            'variant_id' => 42,
            'quantity' => 10,
            'currency' => 'USD',
        ]);

        expect($result)->toBeInstanceOf(CartItemData::class)
            ->and($result->id)->toBe('P-2-00:backing=iron-on')
            ->and($result->price)->toBe(6.02)
            ->and($result->conditions)->toHaveCount(1);
    });

    test('addToCart() adds item to the cart and returns CartItem', function (): void {
        Http::fake([
            '*/products/patches/cart-item' => Http::response([
                'data' => [
                    'id' => 'P-2-00',
                    'name' => 'Custom Patches - 2 Inch',
                    'price' => 6.02,
                    'quantity' => 10,
                    'attributes' => ['product_slug' => 'patches'],
                    'conditions' => [],
                ],
            ]),
        ]);

        $storage = new MockStorage;
        $cart = new Cart($storage);

        $item = makeActionClient()->addToCart('patches', [
            'variant_id' => 42,
            'quantity' => 10,
            'currency' => 'USD',
        ], $cart);

        expect($item)->toBeInstanceOf(CartItem::class)
            ->and($item->id)->toBe('P-2-00')
            ->and($item->name)->toBe('Custom Patches - 2 Inch')
            ->and($item->quantity)->toBe(10)
            ->and($cart->count())->toBe(10);
    });

    test('addToCart() maps conditions as ConditionInterface instances on the CartItem', function (): void {
        Http::fake([
            '*/products/patches/cart-item' => Http::response([
                'data' => [
                    'id' => 'P-2-00',
                    'name' => 'Patches',
                    'price' => 6.02,
                    'quantity' => 1,
                    'attributes' => [],
                    'conditions' => [
                        [
                            'name' => 'Addon: Iron-On',
                            'value' => 0.12,
                            'type' => 'fixed',
                            'target' => 'item',
                            'attributes' => [],
                            'order' => 0,
                            'taxable' => true,
                        ],
                    ],
                ],
            ]),
        ]);

        $storage = new MockStorage;
        $cart = new Cart($storage);

        $item = makeActionClient()->addToCart('patches', [
            'variant_id' => 42,
            'quantity' => 1,
            'currency' => 'USD',
        ], $cart);

        expect($item->conditions)->toHaveCount(1)
            ->and($item->conditions->first()->name)->toBe('Addon: Iron-On');
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/CommerceClientActionTest.php`
Expected: FAIL — methods not found on CommerceClient

### Step 3: Add action methods to CommerceClient

Add these methods to `src/Commerce/CommerceClient.php`:

```php
    /**
     * Resolve price for a configured product.
     *
     * @param  array<string, mixed>  $config  {variant_id, quantity, currency, addon_selections}
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function resolvePrice(string $slug, array $config): PriceBreakdownData
    {
        $response = $this->request('post', "/products/{$slug}/resolve-price", $config);

        return PriceBreakdownData::fromArray($response['data']);
    }

    /**
     * Get a cart-ready payload for a configured product.
     *
     * @param  array<string, mixed>  $config  {variant_id, quantity, currency, addon_selections}
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function cartItem(string $slug, array $config): CartItemData
    {
        $response = $this->request('post', "/products/{$slug}/cart-item", $config);

        return CartItemData::fromArray($response['data']);
    }

    /**
     * Fetch a cart-item payload and add it directly to the cart.
     *
     * @param  array<string, mixed>  $config  {variant_id, quantity, currency, addon_selections}
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null): CartItem
    {
        $data = $this->cartItem($slug, $config);

        $cart ??= app(CartInterface::class);
        $cart->addItem($data->toCartArray());

        return $cart->item($data->id);
    }
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Commerce/CommerceClientActionTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Commerce/CommerceClient.php tests/Commerce/CommerceClientActionTest.php
git commit -m "feat: add resolvePrice, cartItem, and addToCart methods"
```

---

## Task 6: Error Handling

**Files:**
- Modify: `src/Commerce/CommerceClient.php` (already has error handling from Task 4)
- Test: `tests/Commerce/CommerceClientErrorTest.php`

### Step 1: Write the failing tests

Create `tests/Commerce/CommerceClientErrorTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceAuthenticationException;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;
use Illuminate\Support\Facades\Http;

function makeErrorClient(): CommerceClient
{
    return new CommerceClient(
        baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
        token: 'test-token',
        timeout: 5,
        cacheEnabled: false,
        cacheTtl: 300,
    );
}

describe('CommerceClient Error Handling', function (): void {

    test('throws CommerceAuthenticationException on 401', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Invalid API token.'],
            ], 401),
        ]);

        makeErrorClient()->products();
    })->throws(CommerceAuthenticationException::class, 'Invalid API token.');

    test('throws CommerceConnectionException on 404', function (): void {
        Http::fake([
            '*/products/nonexistent' => Http::response([
                'error' => ['code' => 'PRODUCT_NOT_FOUND', 'message' => "No active product found with slug 'nonexistent'."],
            ], 404),
        ]);

        makeErrorClient()->product('nonexistent');
    })->throws(CommerceConnectionException::class, "No active product found with slug 'nonexistent'.");

    test('throws CommerceConnectionException on 422', function (): void {
        Http::fake([
            '*/products/patches/resolve-price' => Http::response([
                'error' => ['code' => 'VARIANT_NOT_FOUND', 'message' => 'Variant 9999 not found.'],
            ], 422),
        ]);

        makeErrorClient()->resolvePrice('patches', [
            'variant_id' => 9999,
            'quantity' => 1,
            'currency' => 'USD',
        ]);
    })->throws(CommerceConnectionException::class, 'Variant 9999 not found.');

    test('throws CommerceConnectionException on 503', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'error' => ['code' => 'API_DISABLED', 'message' => 'API is currently disabled.'],
            ], 503),
        ]);

        makeErrorClient()->products();
    })->throws(CommerceConnectionException::class, 'API is currently disabled.');

    test('throws CommerceConnectionException on connection failure', function (): void {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        makeErrorClient()->products();
    })->throws(CommerceConnectionException::class, 'Could not connect to commerce API');

    test('CommerceConnectionException includes HTTP status code', function (): void {
        Http::fake([
            '*/products/nonexistent' => Http::response([
                'error' => ['code' => 'PRODUCT_NOT_FOUND', 'message' => 'Not found.'],
            ], 404),
        ]);

        try {
            makeErrorClient()->product('nonexistent');
        } catch (CommerceConnectionException $e) {
            expect($e->getCode())->toBe(404);
        }
    });
});
```

**Step 2: Run test to verify it passes (error handling was built into Task 4)**

Run: `vendor/bin/pest tests/Commerce/CommerceClientErrorTest.php`
Expected: PASS — the `request()` method already handles all these cases

If any test fails, adjust the `request()` method in CommerceClient accordingly.

**Step 3: Commit**

```bash
git add tests/Commerce/CommerceClientErrorTest.php
git commit -m "test: add error handling tests for CommerceClient"
```

---

## Task 7: Caching for GET Endpoints

**Files:**
- Modify: `src/Commerce/CommerceClient.php` (caching already built into `get()` method from Task 4)
- Test: `tests/Commerce/CommerceClientCacheTest.php`

### Step 1: Write the tests

Create `tests/Commerce/CommerceClientCacheTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('CommerceClient Caching', function (): void {

    test('GET requests are cached when caching is enabled', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [['slug' => 'p1', 'name' => 'Product 1', 'type' => 'simple']],
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'path' => ''],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        // First call hits the API
        $client->products();
        // Second call should use cache
        $client->products();

        Http::assertSentCount(1);
    });

    test('GET requests bypass cache when caching is disabled', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [],
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: false,
            cacheTtl: 300,
        );

        $client->products();
        $client->products();

        Http::assertSentCount(2);
    });

    test('POST requests are never cached', function (): void {
        Http::fake([
            '*/products/patches/resolve-price' => Http::response([
                'data' => [
                    'product_slug' => 'patches',
                    'variant' => null,
                    'quantity' => 1,
                    'currency' => 'USD',
                    'unit_price' => '5.00',
                    'tier_applied' => null,
                    'addons' => [],
                    'line_total' => '5.00',
                ],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        $client->resolvePrice('patches', ['quantity' => 1, 'currency' => 'USD']);
        $client->resolvePrice('patches', ['quantity' => 1, 'currency' => 'USD']);

        Http::assertSentCount(2);
    });

    test('different query params produce different cache keys', function (): void {
        Http::fake([
            '*/products*' => Http::response([
                'data' => [],
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0, 'path' => ''],
            ]),
        ]);

        $client = new CommerceClient(
            baseUrl: 'https://app-a.test/api/flexi-commerce/v1',
            token: 'test-token',
            cacheEnabled: true,
            cacheTtl: 300,
        );

        $client->products(['page' => 1]);
        $client->products(['page' => 2]);

        Http::assertSentCount(2);
    });
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Commerce/CommerceClientCacheTest.php`
Expected: PASS — caching is already built into the `get()` method from Task 4.

If the cache tests fail, adjust the `get()` method.

**Step 3: Commit**

```bash
git add tests/Commerce/CommerceClientCacheTest.php
git commit -m "test: add caching tests for CommerceClient"
```

---

## Task 8: Service Provider Registration

**Files:**
- Modify: `src/CartServiceProvider.php`
- Test: `tests/Commerce/CommerceServiceProviderTest.php`

### Step 1: Write the failing test

Create `tests/Commerce/CommerceServiceProviderTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\CommerceClient;

describe('Commerce Service Provider', function (): void {

    test('CommerceClient is not bound when commerce is disabled', function (): void {
        config()->set('flexicart.commerce.enabled', false);

        expect(app()->bound(CommerceClient::class))->toBeFalse();
    });

    test('CommerceClient is bound as singleton when commerce is enabled', function (): void {
        config()->set('flexicart.commerce.enabled', true);
        config()->set('flexicart.commerce.base_url', 'https://app-a.test/api');
        config()->set('flexicart.commerce.token', 'test-token');

        // Re-register to pick up config change
        $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
        $provider->packageRegistered();

        expect(app()->bound(CommerceClient::class))->toBeTrue();

        $client1 = app(CommerceClient::class);
        $client2 = app(CommerceClient::class);

        expect($client1)->toBeInstanceOf(CommerceClient::class)
            ->and($client1)->toBe($client2); // same instance (singleton)
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/CommerceServiceProviderTest.php`
Expected: FAIL — CommerceClient is never bound

### Step 3: Register the client in the service provider

Modify `src/CartServiceProvider.php` — add to the end of `packageRegistered()`:

```php
        // Bind CommerceClient when enabled
        if ($this->app['config']['flexicart.commerce.enabled'] ?? false) {
            $this->app->singleton(CommerceClient::class, function (Application $app): CommerceClient {
                /** @var \Illuminate\Config\Repository $config */
                $config = $app['config'];

                return new CommerceClient(
                    baseUrl: (string) $config->get('flexicart.commerce.base_url', ''),
                    token: (string) $config->get('flexicart.commerce.token', ''),
                    timeout: (int) $config->get('flexicart.commerce.timeout', 10),
                    cacheEnabled: (bool) $config->get('flexicart.commerce.cache.enabled', true),
                    cacheTtl: (int) $config->get('flexicart.commerce.cache.ttl', 300),
                );
            });
        }
```

Add the import at the top of `CartServiceProvider.php`:

```php
use Daikazu\Flexicart\Commerce\CommerceClient;
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Commerce/CommerceServiceProviderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/CartServiceProvider.php tests/Commerce/CommerceServiceProviderTest.php
git commit -m "feat: register CommerceClient in service provider when enabled"
```

---

## Task 9: Full Test Suite Verification

**Step 1: Run all tests**

Run: `composer test`
Expected: ALL PASS — no regressions in existing cart tests

**Step 2: Run formatter**

Run: `composer format`
Expected: No changes (or auto-fixed)

**Step 3: Run static analysis**

Run: `composer analyse`
Expected: Check for new errors. If there are new errors from the Commerce code, fix them. Pre-existing errors are acceptable.

**Step 4: Commit if any fixes were needed**

```bash
git add -A
git commit -m "fix: address formatter/PHPStan issues in commerce client"
```
