# Local Commerce Driver Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a `LocalCommerceDriver` to flexicart that calls flexi-commerce models/actions directly when both packages are in the same app, behind a shared `CommerceClientInterface`.

**Architecture:** Extract a `CommerceClientInterface` from the existing `CommerceClient`. Create a `LocalCommerceDriver` implementing the same interface using soft-dependency resolution (class_exists checks). The service provider auto-detects which driver to bind based on whether flexi-commerce is installed, with a config override (`commerce.driver`).

**Tech Stack:** PHP 8.3+, Laravel 11/12, Pest 4, Orchestra Testbench, flexi-commerce (soft dependency)

---

### Task 1: CommerceClientInterface

Extract an interface from the existing `CommerceClient` public methods. Update `CommerceClient` to implement it. Update service provider binding target.

**Files:**
- Create: `src/Contracts/CommerceClientInterface.php`
- Modify: `src/Commerce/CommerceClient.php`
- Modify: `src/CartServiceProvider.php`
- Test: `tests/Commerce/CommerceServiceProviderTest.php`

**Step 1: Write the failing test**

Add a test that resolves `CommerceClientInterface` from the container:

```php
// In tests/Commerce/CommerceServiceProviderTest.php
// Add this use at the top:
use Daikazu\Flexicart\Contracts\CommerceClientInterface;

// Add this test inside the describe block:
test('CommerceClientInterface resolves to CommerceClient when commerce is enabled', function (): void {
    config()->set('flexicart.commerce.enabled', true);
    config()->set('flexicart.commerce.base_url', 'https://app-a.test/api');
    config()->set('flexicart.commerce.token', 'test-token');

    $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
    $provider->packageRegistered();

    expect(app()->bound(CommerceClientInterface::class))->toBeTrue();
    expect(app(CommerceClientInterface::class))->toBeInstanceOf(CommerceClient::class);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/CommerceServiceProviderTest.php`
Expected: FAIL — `CommerceClientInterface` class not found

**Step 3: Create the interface**

Create `src/Contracts/CommerceClientInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Contracts;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Commerce\DTOs\CartItemData;
use Daikazu\Flexicart\Commerce\DTOs\CollectionData;
use Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData;
use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Illuminate\Pagination\LengthAwarePaginator;

interface CommerceClientInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function products(array $filters = []): LengthAwarePaginator;

    public function product(string $slug): ProductData;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collections(array $filters = []): LengthAwarePaginator;

    public function collection(string $slug): CollectionData;

    /**
     * @param  array<string, mixed>  $config
     */
    public function resolvePrice(string $slug, array $config): PriceBreakdownData;

    /**
     * @param  array<string, mixed>  $config
     */
    public function cartItem(string $slug, array $config): CartItemData;

    /**
     * @param  array<string, mixed>  $config
     */
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null): CartItem;
}
```

**Step 4: Update CommerceClient to implement the interface**

In `src/Commerce/CommerceClient.php`, change the class declaration:

```php
// Before:
final class CommerceClient

// After:
final class CommerceClient implements \Daikazu\Flexicart\Contracts\CommerceClientInterface
```

Add the import at the top:

```php
use Daikazu\Flexicart\Contracts\CommerceClientInterface;
```

And update the class line to:

```php
final class CommerceClient implements CommerceClientInterface
```

**Step 5: Update CartServiceProvider to bind the interface**

In `src/CartServiceProvider.php`, change the commerce binding block (lines 72-85) to:

```php
// Bind CommerceClient when enabled
if ($this->app['config']['flexicart.commerce.enabled'] ?? false) {
    $this->app->singleton(CommerceClientInterface::class, function (Application $app): CommerceClient {
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

    // Keep concrete alias for backwards compatibility
    $this->app->alias(CommerceClientInterface::class, CommerceClient::class);
}
```

Add the import at the top:

```php
use Daikazu\Flexicart\Contracts\CommerceClientInterface;
```

**Step 6: Update existing service provider tests**

The existing test `CommerceClient is bound as singleton when commerce is enabled` still works because of the alias. Update both existing tests to also check the interface:

```php
test('CommerceClient is not bound when commerce is disabled', function (): void {
    config()->set('flexicart.commerce.enabled', false);

    expect(app()->bound(CommerceClient::class))->toBeFalse();
    expect(app()->bound(CommerceClientInterface::class))->toBeFalse();
});

test('CommerceClient is bound as singleton when commerce is enabled', function (): void {
    config()->set('flexicart.commerce.enabled', true);
    config()->set('flexicart.commerce.base_url', 'https://app-a.test/api');
    config()->set('flexicart.commerce.token', 'test-token');

    $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
    $provider->packageRegistered();

    expect(app()->bound(CommerceClient::class))->toBeTrue();

    $client1 = app(CommerceClient::class);
    $client2 = app(CommerceClient::class);

    expect($client1)->toBeInstanceOf(CommerceClient::class)
        ->and($client1)->toBe($client2);
});
```

**Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Commerce/CommerceServiceProviderTest.php`
Expected: 3 tests PASS

**Step 8: Run full commerce suite**

Run: `vendor/bin/pest tests/Commerce/`
Expected: All 35 tests PASS

**Step 9: Commit**

```bash
git add src/Contracts/CommerceClientInterface.php src/Commerce/CommerceClient.php src/CartServiceProvider.php tests/Commerce/CommerceServiceProviderTest.php
git commit -m "feat: extract CommerceClientInterface and update bindings"
```

---

### Task 2: Config — Add Driver Key

Add the `driver` config key to the commerce block.

**Files:**
- Modify: `config/flexicart.php`
- Test: `tests/Commerce/CommerceConfigTest.php`

**Step 1: Write the failing test**

Add a test for the driver config key in `tests/Commerce/CommerceConfigTest.php`:

```php
test('commerce driver defaults to auto', function (): void {
    expect(config('flexicart.commerce.driver'))->toBe('auto');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/CommerceConfigTest.php`
Expected: FAIL — driver key is null

**Step 3: Add the driver config key**

In `config/flexicart.php`, update the `commerce` block to add `driver` after `enabled`:

```php
'commerce' => [
    'enabled'  => env('FLEXI_COMMERCE_ENABLED', false),
    'driver'   => env('FLEXI_COMMERCE_DRIVER', 'auto'),
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
Expected: 3 tests PASS

**Step 5: Commit**

```bash
git add config/flexicart.php tests/Commerce/CommerceConfigTest.php
git commit -m "feat: add commerce.driver config key with auto default"
```

---

### Task 3: Service Provider — Driver Selection Logic

Update the service provider to select between API and local drivers based on config and auto-detection.

**Files:**
- Modify: `src/CartServiceProvider.php`
- Test: `tests/Commerce/CommerceServiceProviderTest.php`

**Step 1: Write the failing tests**

Add these tests to `tests/Commerce/CommerceServiceProviderTest.php`:

```php
test('driver=api always binds CommerceClient regardless of local availability', function (): void {
    config()->set('flexicart.commerce.enabled', true);
    config()->set('flexicart.commerce.driver', 'api');
    config()->set('flexicart.commerce.base_url', 'https://app-a.test/api');
    config()->set('flexicart.commerce.token', 'test-token');

    $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
    $provider->packageRegistered();

    expect(app(CommerceClientInterface::class))->toBeInstanceOf(CommerceClient::class);
});

test('driver=local throws when flexi-commerce is not installed', function (): void {
    config()->set('flexicart.commerce.enabled', true);
    config()->set('flexicart.commerce.driver', 'local');

    $provider = app(\Daikazu\Flexicart\CartServiceProvider::class, ['app' => app()]);
    $provider->packageRegistered();

    app(CommerceClientInterface::class);
})->throws(\RuntimeException::class, 'flexi-commerce');
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Commerce/CommerceServiceProviderTest.php`
Expected: FAIL — driver config not yet wired

**Step 3: Implement driver selection logic**

Replace the commerce binding block in `src/CartServiceProvider.php` with:

```php
// Bind commerce driver when enabled
if ($this->app['config']['flexicart.commerce.enabled'] ?? false) {
    $this->app->singleton(CommerceClientInterface::class, function (Application $app): CommerceClientInterface {
        /** @var \Illuminate\Config\Repository $config */
        $config = $app['config'];

        $driver = (string) $config->get('flexicart.commerce.driver', 'auto');

        if ($driver === 'local' || ($driver === 'auto' && $this->flexiCommerceInstalled())) {
            if (! $this->flexiCommerceInstalled()) {
                throw new \RuntimeException(
                    'Cannot use local commerce driver: flexi-commerce package is not installed.'
                );
            }

            return new \Daikazu\Flexicart\Commerce\LocalCommerceDriver;
        }

        return new CommerceClient(
            baseUrl: (string) $config->get('flexicart.commerce.base_url', ''),
            token: (string) $config->get('flexicart.commerce.token', ''),
            timeout: (int) $config->get('flexicart.commerce.timeout', 10),
            cacheEnabled: (bool) $config->get('flexicart.commerce.cache.enabled', true),
            cacheTtl: (int) $config->get('flexicart.commerce.cache.ttl', 300),
        );
    });

    // Keep concrete aliases for backwards compatibility
    $this->app->alias(CommerceClientInterface::class, CommerceClient::class);
    $this->app->alias(CommerceClientInterface::class, \Daikazu\Flexicart\Commerce\LocalCommerceDriver::class);
}
```

Add the helper method to `CartServiceProvider`:

```php
private function flexiCommerceInstalled(): bool
{
    return class_exists(\Daikazu\FlexiCommerce\FlexiCommerceServiceProvider::class);
}
```

**Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Commerce/CommerceServiceProviderTest.php`
Expected: All tests PASS (the `driver=local` test should throw as expected since flexi-commerce isn't installed in the test environment)

**Step 5: Commit**

```bash
git add src/CartServiceProvider.php tests/Commerce/CommerceServiceProviderTest.php
git commit -m "feat: add driver selection logic to service provider"
```

---

### Task 4: LocalCommerceDriver — Scaffold with Product Methods

Create the `LocalCommerceDriver` class with `products()` and `product()` methods that query flexi-commerce models directly.

**Files:**
- Create: `src/Commerce/LocalCommerceDriver.php`
- Create: `tests/Commerce/LocalCommerceDriverTest.php`

**Important context:** This class uses soft dependencies. All flexi-commerce classes are referenced via fully qualified class names resolved at runtime. The class will only be instantiated when flexi-commerce is installed, so the classes are guaranteed to exist at call time.

**Step 1: Write the failing tests**

Create `tests/Commerce/LocalCommerceDriverTest.php`:

```php
<?php

declare(strict_types=1);

use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Daikazu\Flexicart\Commerce\LocalCommerceDriver;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;

beforeEach(function (): void {
    if (! class_exists(\Daikazu\FlexiCommerce\FlexiCommerceServiceProvider::class)) {
        $this->markTestSkipped('flexi-commerce package is not installed.');
    }

    // Boot flexi-commerce to register migrations
    $provider = app(\Daikazu\FlexiCommerce\FlexiCommerceServiceProvider::class, ['app' => app()]);
    $provider->register();
    $provider->boot();

    $this->driver = new LocalCommerceDriver;
});

describe('LocalCommerceDriver Products', function (): void {
    test('products() returns paginated ProductData', function (): void {
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => 'active',
            'type' => 'simple',
        ]);

        $result = $this->driver->products();

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result)->toHaveCount(1)
            ->and($result->items()[0])->toBeInstanceOf(ProductData::class)
            ->and($result->items()[0]->slug)->toBe('test-product');
    });

    test('products() excludes inactive products', function (): void {
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Active',
            'slug' => 'active',
            'status' => 'active',
            'type' => 'simple',
        ]);
        \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Draft',
            'slug' => 'draft',
            'status' => 'draft',
            'type' => 'simple',
        ]);

        $result = $this->driver->products();

        expect($result)->toHaveCount(1)
            ->and($result->items()[0]->slug)->toBe('active');
    });

    test('product() returns ProductData for active product', function (): void {
        $product = \Daikazu\FlexiCommerce\Models\Product::create([
            'name' => 'Patches',
            'slug' => 'patches',
            'status' => 'active',
            'type' => 'configurable',
        ]);

        $result = $this->driver->product('patches');

        expect($result)->toBeInstanceOf(ProductData::class)
            ->and($result->slug)->toBe('patches')
            ->and($result->name)->toBe('Patches')
            ->and($result->type)->toBe('configurable');
    });

    test('product() throws CommerceConnectionException for non-existent slug', function (): void {
        $this->driver->product('nonexistent');
    })->throws(CommerceConnectionException::class, 'nonexistent');
});
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: FAIL — `LocalCommerceDriver` class not found

**Step 3: Create the LocalCommerceDriver class**

Create `src/Commerce/LocalCommerceDriver.php`:

```php
<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce;

use Daikazu\Flexicart\CartItem;
use Daikazu\Flexicart\Commerce\DTOs\CartItemData;
use Daikazu\Flexicart\Commerce\DTOs\CollectionData;
use Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData;
use Daikazu\Flexicart\Commerce\DTOs\ProductData;
use Daikazu\Flexicart\Commerce\Exceptions\CommerceConnectionException;
use Daikazu\Flexicart\Contracts\CartInterface;
use Daikazu\Flexicart\Contracts\CommerceClientInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class LocalCommerceDriver implements CommerceClientInterface
{
    public function products(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $page = (int) ($filters['page'] ?? 1);

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = \Daikazu\FlexiCommerce\Models\Product::query()
            ->where('status', \Daikazu\FlexiCommerce\Enums\ProductStatus::Active)
            ->with('prices')
            ->orderBy('name');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn ($product) => ProductData::fromArray($this->productListToArray($product)))
            ->all();

        return new LengthAwarePaginator(
            items: $items,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
        );
    }

    public function product(string $slug): ProductData
    {
        $product = \Daikazu\FlexiCommerce\Models\Product::query()
            ->where('slug', $slug)
            ->where('status', \Daikazu\FlexiCommerce\Enums\ProductStatus::Active)
            ->with([
                'prices',
                'priceTiers.prices',
                'options.values',
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.optionValues',
                'variants.prices',
                'variants.priceTiers.prices',
                'addonGroups' => fn ($q) => $q->wherePivot('is_active', true),
                'addonGroups.items' => fn ($q) => $q->where('is_active', true),
                'addonGroups.items.addon',
                'addonGroups.items.modifiers.prices',
                'addonGroups.items.modifiers.priceTiers.prices',
            ])
            ->first();

        if ($product === null) {
            throw new CommerceConnectionException(
                "No active product found with slug '{$slug}'."
            );
        }

        return ProductData::fromArray($this->productToArray($product));
    }

    public function collections(array $filters = []): LengthAwarePaginator
    {
        // Implemented in Task 5
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function collection(string $slug): CollectionData
    {
        // Implemented in Task 5
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function resolvePrice(string $slug, array $config): PriceBreakdownData
    {
        // Implemented in Task 6
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function cartItem(string $slug, array $config): CartItemData
    {
        // Implemented in Task 6
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function addToCart(string $slug, array $config, ?CartInterface $cart = null): CartItem
    {
        // Implemented in Task 7
        throw new \BadMethodCallException('Not implemented yet.');
    }

    /**
     * Convert a Product model to the list resource array shape.
     *
     * @return array<string, mixed>
     */
    private function productListToArray(object $product): array
    {
        return [
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'status' => $product->status?->value,
            'type' => $product->type?->value,
            'meta' => $product->meta ?? [],
            'prices' => $product->prices->map(fn ($p) => $this->priceToArray($p))->all(),
        ];
    }

    /**
     * Convert a Product model (fully loaded) to the detail resource array shape.
     *
     * @return array<string, mixed>
     */
    private function productToArray(object $product): array
    {
        return [
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'status' => $product->status?->value,
            'type' => $product->type?->value,
            'meta' => $product->meta ?? [],
            'prices' => $product->prices->map(fn ($p) => $this->priceToArray($p))->all(),
            'price_tiers' => $product->priceTiers->map(fn ($t) => $this->priceTierToArray($t))->all(),
            'options' => $product->options->map(fn ($o) => $this->optionToArray($o))->all(),
            'variants' => $product->variants->map(fn ($v) => $this->variantToArray($v))->all(),
            'addon_groups' => $product->addonGroups->map(fn ($g) => $this->addonGroupToArray($g))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceToArray(object $price): array
    {
        return [
            'key' => $price->key,
            'currency' => $price->currency,
            'amount' => $price->money()->jsonSerialize(),
            'amount_minor' => $price->amount_minor,
            'starts_at' => $price->starts_at?->toIso8601String(),
            'ends_at' => $price->ends_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceTierToArray(object $tier): array
    {
        return [
            'key' => $tier->key,
            'currency' => $tier->currency,
            'min_qty' => $tier->min_qty,
            'max_qty' => $tier->max_qty,
            'priority' => $tier->priority,
            'starts_at' => $tier->starts_at?->toIso8601String(),
            'ends_at' => $tier->ends_at?->toIso8601String(),
            'prices' => $tier->prices->map(fn ($p) => $this->priceToArray($p))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionToArray(object $option): array
    {
        return [
            'id' => $option->id,
            'name' => $option->name,
            'code' => $option->code,
            'is_variant' => $option->is_variant,
            'is_required' => $option->is_required,
            'display_type' => $option->display_type,
            'sort_order' => $option->sort_order,
            'meta' => $option->meta,
            'values' => $option->values->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'code' => $v->code,
                'is_active' => $v->is_active,
                'sort_order' => $v->sort_order,
                'meta' => $v->meta,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function variantToArray(object $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'name' => $variant->name,
            'is_active' => $variant->is_active,
            'sort_order' => $variant->sort_order,
            'signature' => $variant->signature,
            'meta' => $variant->meta,
            'option_values' => $variant->optionValues->map(fn ($ov) => [
                'id' => $ov->id,
                'name' => $ov->name,
                'code' => $ov->code,
                'is_active' => $ov->is_active,
                'sort_order' => $ov->sort_order,
                'meta' => $ov->meta,
            ])->all(),
            'prices' => $variant->prices->map(fn ($p) => $this->priceToArray($p))->all(),
            'price_tiers' => $variant->priceTiers->map(fn ($t) => $this->priceTierToArray($t))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addonGroupToArray(object $group): array
    {
        return [
            'id' => $group->id,
            'code' => $group->code,
            'name' => $group->name,
            'selection_type' => $group->selection_type?->value,
            'min_selected' => $group->min_selected,
            'max_selected' => $group->max_selected,
            'free_selection_limit' => $group->free_selection_limit,
            'is_auto_applied' => $group->is_auto_applied,
            'meta' => $group->meta,
            'items' => $group->items->map(fn ($item) => [
                'id' => $item->id,
                'addon_code' => $item->addon->code,
                'addon_name' => $item->addon->name,
                'default_selected' => $item->default_selected,
                'is_active' => $item->is_active,
                'is_free_eligible' => $item->is_free_eligible,
                'sort_order' => $item->sort_order,
                'meta' => $item->meta,
                'modifiers' => $item->modifiers->map(fn ($m) => [
                    'id' => $m->id,
                    'modifier_type' => $m->modifier_type?->value,
                    'applies_to' => $m->applies_to?->value,
                    'percent' => $m->percent,
                    'rounding_mode' => $m->rounding_mode?->value,
                    'product_variant_id' => $m->product_variant_id,
                    'min_qty' => $m->min_qty,
                    'max_qty' => $m->max_qty,
                    'meta' => $m->meta,
                    'prices' => $m->prices->map(fn ($p) => $this->priceToArray($p))->all(),
                    'price_tiers' => $m->priceTiers->map(fn ($t) => $this->priceTierToArray($t))->all(),
                ])->all(),
            ])->all(),
        ];
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: All 4 tests PASS (or skipped if flexi-commerce not installed)

**Step 5: Commit**

```bash
git add src/Commerce/LocalCommerceDriver.php tests/Commerce/LocalCommerceDriverTest.php
git commit -m "feat: add LocalCommerceDriver with product methods"
```

---

### Task 5: LocalCommerceDriver — Collection Methods

Add `collections()` and `collection()` methods.

**Files:**
- Modify: `src/Commerce/LocalCommerceDriver.php`
- Modify: `tests/Commerce/LocalCommerceDriverTest.php`

**Step 1: Write the failing tests**

Add to `tests/Commerce/LocalCommerceDriverTest.php`:

```php
describe('LocalCommerceDriver Collections', function (): void {
    test('collections() returns paginated CollectionData', function (): void {
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name' => 'Summer Collection',
            'slug' => 'summer',
            'is_active' => true,
            'type' => 'category',
        ]);

        $result = $this->driver->collections();

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result)->toHaveCount(1)
            ->and($result->items()[0])->toBeInstanceOf(\Daikazu\Flexicart\Commerce\DTOs\CollectionData::class)
            ->and($result->items()[0]->slug)->toBe('summer');
    });

    test('collections() excludes inactive collections', function (): void {
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name' => 'Active',
            'slug' => 'active-col',
            'is_active' => true,
            'type' => 'category',
        ]);
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name' => 'Inactive',
            'slug' => 'inactive-col',
            'is_active' => false,
            'type' => 'category',
        ]);

        $result = $this->driver->collections();

        expect($result)->toHaveCount(1)
            ->and($result->items()[0]->slug)->toBe('active-col');
    });

    test('collection() returns CollectionData for active collection', function (): void {
        \Daikazu\FlexiCommerce\Models\ProductCollection::create([
            'name' => 'Summer',
            'slug' => 'summer',
            'is_active' => true,
            'type' => 'merchandising',
        ]);

        $result = $this->driver->collection('summer');

        expect($result)->toBeInstanceOf(\Daikazu\Flexicart\Commerce\DTOs\CollectionData::class)
            ->and($result->slug)->toBe('summer')
            ->and($result->name)->toBe('Summer');
    });

    test('collection() throws CommerceConnectionException for non-existent slug', function (): void {
        $this->driver->collection('nonexistent');
    })->throws(CommerceConnectionException::class, 'nonexistent');
});
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: FAIL — `BadMethodCallException: Not implemented yet.`

**Step 3: Implement collection methods**

Replace the two placeholder methods in `src/Commerce/LocalCommerceDriver.php`:

```php
public function collections(array $filters = []): LengthAwarePaginator
{
    $perPage = min((int) ($filters['per_page'] ?? 15), 100);
    $page = (int) ($filters['page'] ?? 1);

    /** @var \Illuminate\Database\Eloquent\Builder $query */
    $query = \Daikazu\FlexiCommerce\Models\ProductCollection::query()
        ->where('is_active', true)
        ->with('children')
        ->orderBy('name');

    $paginator = $query->paginate($perPage, ['*'], 'page', $page);

    $items = $paginator->getCollection()
        ->map(fn ($collection) => CollectionData::fromArray($this->collectionToArray($collection)))
        ->all();

    return new LengthAwarePaginator(
        items: $items,
        total: $paginator->total(),
        perPage: $paginator->perPage(),
        currentPage: $paginator->currentPage(),
    );
}

public function collection(string $slug): CollectionData
{
    $collection = \Daikazu\FlexiCommerce\Models\ProductCollection::query()
        ->where('slug', $slug)
        ->where('is_active', true)
        ->with([
            'children',
            'products' => fn ($q) => $q->where('status', \Daikazu\FlexiCommerce\Enums\ProductStatus::Active),
            'products.prices',
        ])
        ->first();

    if ($collection === null) {
        throw new CommerceConnectionException(
            "No active collection found with slug '{$slug}'."
        );
    }

    return CollectionData::fromArray($this->collectionToArray($collection));
}
```

Also add the `collectionToArray` private method:

```php
/**
 * @return array<string, mixed>
 */
private function collectionToArray(object $collection): array
{
    return [
        'slug' => $collection->slug,
        'name' => $collection->name,
        'type' => $collection->type?->value,
        'description' => $collection->description,
        'is_active' => $collection->is_active,
        'parent_id' => $collection->parent_id,
        'meta' => $collection->meta ?? [],
        'products' => $collection->relationLoaded('products')
            ? $collection->products->map(fn ($p) => $this->productListToArray($p))->all()
            : [],
        'children' => $collection->relationLoaded('children')
            ? $collection->children->map(fn ($c) => $this->collectionToArray($c))->all()
            : [],
    ];
}
```

**Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: All 8 tests PASS

**Step 5: Commit**

```bash
git add src/Commerce/LocalCommerceDriver.php tests/Commerce/LocalCommerceDriverTest.php
git commit -m "feat: add collection methods to LocalCommerceDriver"
```

---

### Task 6: LocalCommerceDriver — resolvePrice and cartItem

Implement `resolvePrice()` and `cartItem()` by calling `ResolvePriceAction` directly.

**Files:**
- Modify: `src/Commerce/LocalCommerceDriver.php`
- Modify: `tests/Commerce/LocalCommerceDriverTest.php`

**Step 1: Write the failing tests**

Add to `tests/Commerce/LocalCommerceDriverTest.php`. First, add a new `beforeEach` helper for creating a full product with variant and addon (add near the top, after the existing `beforeEach`):

```php
function createConfiguredProduct(): array
{
    $product = \Daikazu\FlexiCommerce\Models\Product::create([
        'name' => 'Patches',
        'slug' => 'patches',
        'status' => 'active',
        'type' => 'configurable',
    ]);

    $option = \Daikazu\FlexiCommerce\Models\ProductOption::create([
        'product_id' => $product->id,
        'name' => 'Size',
        'code' => 'size',
        'is_variant' => true,
        'is_required' => true,
    ]);

    $value = \Daikazu\FlexiCommerce\Models\ProductOptionValue::create([
        'product_option_id' => $option->id,
        'name' => '2 Inch',
        'code' => '2-00',
        'is_active' => true,
    ]);

    $variant = \Daikazu\FlexiCommerce\Models\ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'PATCHES-2-00',
        'name' => '2 Inch',
        'is_active' => true,
        'signature' => 'size:2-00',
    ]);

    $variant->optionValues()->attach($value->id, ['product_option_id' => $option->id]);

    $variant->prices()->create([
        'key' => \Daikazu\FlexiCommerce\Enums\PriceKey::Retail->value,
        'currency' => 'USD',
        'amount_minor' => 602,
    ]);

    $group = \Daikazu\FlexiCommerce\Models\AddonGroup::create([
        'code' => 'backing',
        'name' => 'Backing',
        'selection_type' => 'single',
        'min_selected' => 0,
        'max_selected' => 1,
        'free_selection_limit' => 0,
    ]);

    $addon = \Daikazu\FlexiCommerce\Models\Addon::create([
        'code' => 'iron-on',
        'name' => 'Iron-On',
        'is_active' => true,
    ]);

    $item = \Daikazu\FlexiCommerce\Models\AddonGroupItem::create([
        'addon_group_id' => $group->id,
        'addon_id' => $addon->id,
        'default_selected' => false,
        'is_active' => true,
        'is_free_eligible' => false,
    ]);

    $modifier = \Daikazu\FlexiCommerce\Models\AddonModifier::create([
        'addon_group_item_id' => $item->id,
        'modifier_type' => 'per_qty',
        'applies_to' => 'unit',
    ]);

    $modifier->prices()->create([
        'key' => \Daikazu\FlexiCommerce\Enums\PriceKey::ModifierAmount->value,
        'currency' => 'USD',
        'amount_minor' => 12,
    ]);

    $product->addonGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

    return ['product' => $product, 'variant' => $variant];
}
```

Then add the tests:

```php
describe('LocalCommerceDriver Price Resolution', function (): void {
    test('resolvePrice() returns PriceBreakdownData', function (): void {
        $data = createConfiguredProduct();

        $result = $this->driver->resolvePrice('patches', [
            'variant_id' => $data['variant']->id,
            'quantity' => 10,
            'currency' => 'USD',
        ]);

        expect($result)->toBeInstanceOf(\Daikazu\Flexicart\Commerce\DTOs\PriceBreakdownData::class)
            ->and($result->unitPrice)->toBe('6.02')
            ->and($result->quantity)->toBe(10)
            ->and($result->lineTotal)->toBe('60.20');
    });

    test('resolvePrice() with addon selections', function (): void {
        $data = createConfiguredProduct();

        $result = $this->driver->resolvePrice('patches', [
            'variant_id' => $data['variant']->id,
            'quantity' => 10,
            'currency' => 'USD',
            'addon_selections' => [
                'backing' => ['iron-on' => 1],
            ],
        ]);

        expect($result->lineTotal)->toBe('61.40')
            ->and($result->addons)->toHaveCount(1)
            ->and($result->addons[0]['addon_code'])->toBe('iron-on');
    });

    test('resolvePrice() throws for invalid variant', function (): void {
        createConfiguredProduct();

        $this->driver->resolvePrice('patches', [
            'variant_id' => 9999,
            'quantity' => 1,
            'currency' => 'USD',
        ]);
    })->throws(CommerceConnectionException::class);

    test('resolvePrice() throws for non-existent product', function (): void {
        $this->driver->resolvePrice('nonexistent', [
            'quantity' => 1,
            'currency' => 'USD',
        ]);
    })->throws(CommerceConnectionException::class, 'nonexistent');

    test('cartItem() returns CartItemData', function (): void {
        $data = createConfiguredProduct();

        $result = $this->driver->cartItem('patches', [
            'variant_id' => $data['variant']->id,
            'quantity' => 10,
            'currency' => 'USD',
            'addon_selections' => [
                'backing' => ['iron-on' => 1],
            ],
        ]);

        expect($result)->toBeInstanceOf(\Daikazu\Flexicart\Commerce\DTOs\CartItemData::class)
            ->and($result->price)->toBe(6.02)
            ->and($result->quantity)->toBe(10)
            ->and($result->attributes)->toHaveKeys(['product_slug', 'variant_id', 'sku', 'option_values'])
            ->and($result->attributes['option_values'])->toBe(['size' => '2 Inch'])
            ->and($result->conditions)->toHaveCount(1);
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: FAIL — `BadMethodCallException: Not implemented yet.`

**Step 3: Implement resolvePrice and cartItem**

Replace the placeholders in `src/Commerce/LocalCommerceDriver.php`:

```php
public function resolvePrice(string $slug, array $config): PriceBreakdownData
{
    $product = $this->findActiveProduct($slug);

    try {
        /** @var array<string, mixed> $result */
        $result = (new \Daikazu\FlexiCommerce\Actions\Pricing\ResolvePriceAction)->handle(
            product: $product,
            variantId: isset($config['variant_id']) ? (int) $config['variant_id'] : null,
            quantity: (int) ($config['quantity'] ?? 1),
            currency: strtoupper((string) ($config['currency'] ?? 'USD')),
            addonSelections: $config['addon_selections'] ?? [],
        );
    } catch (\InvalidArgumentException $e) {
        throw new CommerceConnectionException($e->getMessage(), 0, $e);
    }

    return PriceBreakdownData::fromArray($result);
}

public function cartItem(string $slug, array $config): CartItemData
{
    $product = $this->findActiveProduct($slug);

    try {
        /** @var array<string, mixed> $result */
        $result = (new \Daikazu\FlexiCommerce\Actions\Pricing\ResolvePriceAction)->handle(
            product: $product,
            variantId: isset($config['variant_id']) ? (int) $config['variant_id'] : null,
            quantity: (int) ($config['quantity'] ?? 1),
            currency: strtoupper((string) ($config['currency'] ?? 'USD')),
            addonSelections: $config['addon_selections'] ?? [],
        );
    } catch (\InvalidArgumentException $e) {
        throw new CommerceConnectionException($e->getMessage(), 0, $e);
    }

    return CartItemData::fromArray($this->toCartItem($result, $product));
}
```

Add the private helper methods:

```php
/**
 * @return object The Product model instance
 */
private function findActiveProduct(string $slug): object
{
    $product = \Daikazu\FlexiCommerce\Models\Product::query()
        ->where('slug', $slug)
        ->where('status', \Daikazu\FlexiCommerce\Enums\ProductStatus::Active)
        ->first();

    if ($product === null) {
        throw new CommerceConnectionException(
            "No active product found with slug '{$slug}'."
        );
    }

    return $product;
}

/**
 * Transform a ResolvePriceAction result into a cart-item array.
 *
 * @param  array<string, mixed>  $result
 * @return array<string, mixed>
 */
private function toCartItem(array $result, object $product): array
{
    $sku = $result['variant']['sku'] ?? $product->slug;
    $variantName = $result['variant']['name'] ?? null;

    $addonParts = collect($result['addons'])
        ->groupBy('group_code')
        ->map(fn ($items, $group) => $group . '=' . $items->pluck('addon_code')->unique()->implode('+'))
        ->implode(':');

    $cartId = $addonParts !== '' ? "{$sku}:{$addonParts}" : $sku;

    $name = $variantName !== null
        ? "{$product->name} - {$variantName}"
        : $product->name;

    // Build option_values map from variant
    $optionValues = [];
    if ($result['variant'] !== null) {
        $variant = $product->variants()
            ->where('id', $result['variant']['id'])
            ->with('optionValues.option')
            ->first();

        if ($variant !== null) {
            foreach ($variant->optionValues as $ov) {
                $optionValues[$ov->option->code] = $ov->name;
            }
        }
    }

    // Build conditions from addon modifiers
    $conditions = [];
    foreach ($result['addons'] as $i => $addon) {
        if ($addon['is_free']) {
            continue;
        }

        $value = (float) $addon['unit_amount'];
        if ($value == 0.0) {
            continue;
        }

        $appliesTo = $addon['applies_to'] === \Daikazu\FlexiCommerce\Enums\ModifierAppliesTo::Line->value
            ? 'subtotal'
            : 'item';

        $conditions[] = [
            'name' => "Addon: {$addon['name']}",
            'value' => $value,
            'type' => 'fixed',
            'target' => $appliesTo,
            'attributes' => [
                'addon_code' => $addon['addon_code'],
                'group_code' => $addon['group_code'],
                'modifier_id' => $addon['modifier_id'],
            ],
            'order' => $i,
            'taxable' => true,
        ];
    }

    return [
        'id' => $cartId,
        'name' => $name,
        'price' => (float) $result['unit_price'],
        'quantity' => $result['quantity'],
        'attributes' => [
            'product_slug' => $result['product_slug'],
            'variant_id' => $result['variant']['id'] ?? null,
            'sku' => $sku,
            'option_values' => $optionValues,
            'source' => 'flexi-commerce',
            'resolved_at' => now()->toIso8601String(),
        ],
        'conditions' => $conditions,
    ];
}
```

**Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: All 13 tests PASS

**Step 5: Commit**

```bash
git add src/Commerce/LocalCommerceDriver.php tests/Commerce/LocalCommerceDriverTest.php
git commit -m "feat: add resolvePrice and cartItem to LocalCommerceDriver"
```

---

### Task 7: LocalCommerceDriver — addToCart

Implement `addToCart()` which calls `cartItem()` then adds to the cart.

**Files:**
- Modify: `src/Commerce/LocalCommerceDriver.php`
- Modify: `tests/Commerce/LocalCommerceDriverTest.php`

**Step 1: Write the failing test**

Add to `tests/Commerce/LocalCommerceDriverTest.php`:

```php
describe('LocalCommerceDriver addToCart', function (): void {
    test('addToCart() adds item to cart and returns CartItem', function (): void {
        $data = createConfiguredProduct();

        $cart = app(\Daikazu\Flexicart\Contracts\CartInterface::class);

        $cartItem = $this->driver->addToCart('patches', [
            'variant_id' => $data['variant']->id,
            'quantity' => 5,
            'currency' => 'USD',
        ], $cart);

        expect($cartItem)->toBeInstanceOf(\Daikazu\Flexicart\CartItem::class)
            ->and($cartItem->id)->toBe('PATCHES-2-00')
            ->and($cartItem->quantity)->toBe(5)
            ->and($cart->count())->toBe(5);
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: FAIL — `BadMethodCallException: Not implemented yet.`

**Step 3: Implement addToCart**

Replace the placeholder in `src/Commerce/LocalCommerceDriver.php`:

```php
public function addToCart(string $slug, array $config, ?CartInterface $cart = null): CartItem
{
    $data = $this->cartItem($slug, $config);

    $cart ??= app(CartInterface::class);
    $cart->addItem($data->toCartArray());

    return $cart->item($data->id)
        ?? throw new CommerceConnectionException("Failed to add item '{$data->id}' to cart.");
}
```

**Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Commerce/LocalCommerceDriverTest.php`
Expected: All 14 tests PASS

**Step 5: Commit**

```bash
git add src/Commerce/LocalCommerceDriver.php tests/Commerce/LocalCommerceDriverTest.php
git commit -m "feat: add addToCart to LocalCommerceDriver"
```

---

### Task 8: Full Suite Verification

Run the complete test suite, formatter, and static analysis.

**Files:** None (verification only)

**Step 1: Run full commerce test suite**

Run: `vendor/bin/pest tests/Commerce/`
Expected: All tests PASS (35 HTTP driver + ~14 local driver)

**Step 2: Run formatter**

Run: `vendor/bin/pint`
Expected: No formatting changes needed (or apply them)

**Step 3: Run static analysis (if applicable)**

Run: `composer analyse` (if phpstan is configured)
Expected: No new errors

**Step 4: Commit any formatting fixes**

If Pint made changes:
```bash
git add -A
git commit -m "style: apply Laravel Pint formatting"
```
