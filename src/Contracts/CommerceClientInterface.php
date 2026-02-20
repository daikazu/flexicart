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
