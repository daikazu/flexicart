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
     * @param  array<string, mixed>  $filters
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

        return $cart->item($data->id)
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

        $cacheKey = 'flexicart:commerce:' . md5($path . serialize($query));

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
