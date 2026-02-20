# Local Commerce Driver Design

**Goal:** When flexicart and flexi-commerce are installed in the same Laravel application, bypass HTTP API requests and call flexi-commerce models/actions directly — same interface, same DTOs, zero network overhead.

## Architecture

**Pattern:** Interface + two drivers (Approach A). A `CommerceClientInterface` defines the contract. The existing `CommerceClient` becomes the "API driver." A new `LocalCommerceDriver` implements the same interface using flexi-commerce classes directly via soft dependency (no composer require).

**Auto-detection:** The service provider checks `class_exists('Daikazu\FlexiCommerce\FlexiCommerceServiceProvider')` to determine if local mode is available. A config key (`commerce.driver`) allows explicit override: `'auto'` (default), `'api'`, or `'local'`.

## Interface Contract

```php
interface CommerceClientInterface
{
    public function products(array $filters = []): LengthAwarePaginator;
    public function product(string $slug): ProductData;
    public function collections(array $filters = []): LengthAwarePaginator;
    public function collection(string $slug): CollectionData;
    public function resolvePrice(string $slug, array $config): PriceBreakdownData;
    public function cartItem(string $slug, array $config): CartItemData;
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null): CartItem;
}
```

Both `CommerceClient` (HTTP) and `LocalCommerceDriver` implement this interface. Consumer code type-hints against the interface.

## Local Driver — Model-to-DTO Conversion

`LocalCommerceDriver` lives at `src/Commerce/LocalCommerceDriver.php`. All flexi-commerce classes referenced via soft dependency (class_exists checks, string-based resolution).

| Method | Implementation |
|--------|---------------|
| `products()` / `product()` | Query `Product` model directly (same scopes/eager loads as API controller), convert to array matching API resource shape, pass through `ProductData::fromArray()` |
| `collections()` / `collection()` | Same pattern with `ProductCollection` model |
| `resolvePrice()` | Call `ResolvePriceAction::handle()` directly — already returns the exact array shape `PriceBreakdownData::fromArray()` expects |
| `cartItem()` | Call `ResolvePriceAction::handle()` then apply `toCartItem()` transformation (extracted from `PriceResolveController`), pass through `CartItemData::fromArray()` |
| `addToCart()` | Call `cartItem()` then add to cart — same as HTTP driver |

No caching for the local driver. Database queries run fresh each call.

## Config Shape

```php
'commerce' => [
    'enabled'  => env('FLEXI_COMMERCE_ENABLED', false),
    'driver'   => env('FLEXI_COMMERCE_DRIVER', 'auto'), // 'auto', 'api', 'local'
    'base_url' => env('FLEXI_COMMERCE_URL'),             // API driver only
    'token'    => env('FLEXI_COMMERCE_TOKEN'),            // API driver only
    'timeout'  => env('FLEXI_COMMERCE_TIMEOUT', 10),      // API driver only
    'cache'    => [                                        // API driver only
        'enabled' => env('FLEXI_COMMERCE_CACHE', true),
        'ttl'     => env('FLEXI_COMMERCE_CACHE_TTL', 300),
    ],
],
```

## Service Provider Detection Logic

1. Gate: `commerce.enabled` must be true
2. Read `commerce.driver` (default `'auto'`)
3. `'auto'` — `class_exists(FlexiCommerceServiceProvider)` ? bind `LocalCommerceDriver` : bind `CommerceClient`
4. `'api'` — always bind `CommerceClient`
5. `'local'` — always bind `LocalCommerceDriver` (throws if flexi-commerce not installed)
6. Binding target: `CommerceClientInterface`

## Error Handling

- **Not found** — throws `CommerceConnectionException` (same message pattern as API 404)
- **Invalid variant / unsupported currency** — catches `\InvalidArgumentException` from `ResolvePriceAction`, re-throws as `CommerceConnectionException`
- **`CommerceAuthenticationException`** — never thrown by local driver (no auth needed)
- **Input validation** — simple guard clauses matching `ResolvePriceRequest` rules (required quantity, currency format)

## Testing

- `tests/Commerce/LocalCommerceDriverTest.php` — integration tests with real flexi-commerce models, conditionally skipped if package not installed
- `tests/Commerce/CommerceServiceProviderTest.php` — updated to cover auto-detection and driver selection
- Existing HTTP driver tests unchanged
- Both drivers tested for interface conformance (same inputs produce same DTO types)
