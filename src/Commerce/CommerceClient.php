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
use Daikazu\Flexicart\Contracts\CommerceClientInterface;
use Daikazu\Flexicart\Enums\AddItemBehavior;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class CommerceClient implements CommerceClientInterface
{
    private PendingRequest $http;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly ?string $storeId = null,
        private readonly int $timeout = 10,
        private readonly bool $cacheEnabled = true,
        private readonly int $cacheTtl = 300,
    ) {
        $http = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->token)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->storeId !== null) {
            $http = $http->withHeaders(['X-Store-Id' => $this->storeId]);
        }

        $this->http = $http;
    }

    /**
     * List active products (paginated).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ProductData>
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function products(array $filters = []): LengthAwarePaginator
    {
        $response = $this->get('/products', $filters);

        /** @var LengthAwarePaginator<int, ProductData> */
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

        /** @var array<string, mixed> $data */
        $data = $response['data'];

        return ProductData::fromArray($data);
    }

    /**
     * List active collections (paginated).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CollectionData>
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function collections(array $filters = []): LengthAwarePaginator
    {
        $response = $this->get('/collections', $filters);

        /** @var LengthAwarePaginator<int, CollectionData> */
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

        /** @var array<string, mixed> $data */
        $data = $response['data'];

        return CollectionData::fromArray($data);
    }

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

        /** @var array<string, mixed> $data */
        $data = $response['data'];

        return PriceBreakdownData::fromArray($data);
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

        /** @var array<string, mixed> $data */
        $data = $response['data'];

        return CartItemData::fromArray($data);
    }

    /**
     * Fetch a cart-item payload and add it directly to the cart.
     *
     * @param  array<string, mixed>  $config  {variant_id, quantity, currency, addon_selections}
     *
     * @throws CommerceConnectionException
     * @throws CommerceAuthenticationException
     */
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null, ?AddItemBehavior $behavior = null): CartItem
    {
        $data = $this->cartItem($slug, $config);

        $cart ??= app(CartInterface::class);
        $cart->addItem($data->toCartArray(), $behavior);

        // When behavior is New, the ID may have been suffixed — find by base ID or suffixed ID
        $item = $cart->item($data->id);
        if ($item === null && $behavior === AddItemBehavior::New) {
            $item = $cart->items()->last();
        }

        return $item
            ?? throw new CommerceConnectionException("Failed to add item '{$data->id}' to cart.");
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

        $prefix = $this->storeId !== null
            ? "flexicart:commerce:{$this->storeId}:"
            : 'flexicart:commerce:';

        $cacheKey = $prefix . md5($path . serialize($query));

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
    /**
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
                $authMsg = $response->json('error.message', 'Authentication failed.');
                throw new CommerceAuthenticationException(
                    is_string($authMsg) ? $authMsg : 'Authentication failed.'
                );
            }

            if ($response->failed()) {
                $errMsg = $response->json('error.message', "Request failed with status {$response->status()}.");
                throw new CommerceConnectionException(
                    is_string($errMsg) ? $errMsg : "Request failed with status {$response->status()}.",
                    $response->status(),
                );
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

            return $json;
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
     * @template T
     * @param  array<string, mixed>  $response
     * @param  callable(array<string, mixed>): T  $mapper
     * @return LengthAwarePaginator<int, T>
     */
    private function toPaginator(array $response, callable $mapper): LengthAwarePaginator
    {
        /** @var list<array<string, mixed>> $dataItems */
        $dataItems = $response['data'];
        $items = array_map($mapper, $dataItems);

        /** @var array{total: int, per_page: int, current_page: int} $meta */
        $meta = $response['meta'];

        return new LengthAwarePaginator(
            items: $items,
            total: $meta['total'],
            perPage: $meta['per_page'],
            currentPage: $meta['current_page'],
        );
    }
}
