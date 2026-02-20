<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce;

use BadMethodCallException;
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
    /**
     * List active products (paginated).
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws CommerceConnectionException
     */
    public function products(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $page = (int) ($filters['page'] ?? 1);

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

    /**
     * Get a single product by slug.
     *
     * @throws CommerceConnectionException
     */
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
                'addonGroups'       => fn ($q) => $q->wherePivot('is_active', true),
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

    /**
     * List active collections (paginated).
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws CommerceConnectionException
     */
    public function collections(array $filters = []): LengthAwarePaginator
    {
        throw new BadMethodCallException('Not implemented yet.');
    }

    /**
     * Get a single collection by slug.
     *
     * @throws CommerceConnectionException
     */
    public function collection(string $slug): CollectionData
    {
        throw new BadMethodCallException('Not implemented yet.');
    }

    /**
     * Resolve price for a configured product.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws CommerceConnectionException
     */
    public function resolvePrice(string $slug, array $config): PriceBreakdownData
    {
        throw new BadMethodCallException('Not implemented yet.');
    }

    /**
     * Get a cart-ready payload for a configured product.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws CommerceConnectionException
     */
    public function cartItem(string $slug, array $config): CartItemData
    {
        throw new BadMethodCallException('Not implemented yet.');
    }

    /**
     * Fetch a cart-item payload and add it directly to the cart.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws CommerceConnectionException
     */
    public function addToCart(string $slug, array $config, ?CartInterface $cart = null): CartItem
    {
        throw new BadMethodCallException('Not implemented yet.');
    }

    /**
     * Convert a product model to a list-level array (no nested relations).
     *
     * @return array<string, mixed>
     */
    private function productListToArray(object $product): array
    {
        return [
            'slug'        => $product->slug,
            'name'        => $product->name,
            'description' => $product->description,
            'status'      => $product->status?->value,
            'type'        => $product->type?->value,
            'meta'        => $product->meta ?? [],
            'prices'      => $product->prices->map(fn ($p) => $this->priceToArray($p))->all(),
        ];
    }

    /**
     * Convert a product model to a full detail array with all nested relations.
     *
     * @return array<string, mixed>
     */
    private function productToArray(object $product): array
    {
        return [
            'slug'         => $product->slug,
            'name'         => $product->name,
            'description'  => $product->description,
            'status'       => $product->status?->value,
            'type'         => $product->type?->value,
            'meta'         => $product->meta ?? [],
            'prices'       => $product->prices->map(fn ($p) => $this->priceToArray($p))->all(),
            'price_tiers'  => $product->priceTiers->map(fn ($t) => $this->priceTierToArray($t))->all(),
            'options'      => $product->options->map(fn ($o) => $this->optionToArray($o))->all(),
            'variants'     => $product->variants->map(fn ($v) => $this->variantToArray($v))->all(),
            'addon_groups' => $product->addonGroups->map(fn ($g) => $this->addonGroupToArray($g))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceToArray(object $price): array
    {
        return [
            'key'          => $price->key,
            'currency'     => $price->currency,
            'amount'       => $price->money()->jsonSerialize(),
            'amount_minor' => $price->amount_minor,
            'starts_at'    => $price->starts_at?->toIso8601String(),
            'ends_at'      => $price->ends_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceTierToArray(object $tier): array
    {
        return [
            'key'       => $tier->key,
            'currency'  => $tier->currency,
            'min_qty'   => $tier->min_qty,
            'max_qty'   => $tier->max_qty,
            'priority'  => $tier->priority,
            'starts_at' => $tier->starts_at?->toIso8601String(),
            'ends_at'   => $tier->ends_at?->toIso8601String(),
            'prices'    => $tier->prices->map(fn ($p) => $this->priceToArray($p))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionToArray(object $option): array
    {
        return [
            'id'           => $option->id,
            'name'         => $option->name,
            'code'         => $option->code,
            'is_variant'   => $option->is_variant,
            'is_required'  => $option->is_required,
            'display_type' => $option->display_type,
            'sort_order'   => $option->sort_order,
            'meta'         => $option->meta,
            'values'       => $option->values->map(fn ($v) => [
                'id'         => $v->id,
                'name'       => $v->name,
                'code'       => $v->code,
                'is_active'  => $v->is_active,
                'sort_order' => $v->sort_order,
                'meta'       => $v->meta,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function variantToArray(object $variant): array
    {
        return [
            'id'            => $variant->id,
            'sku'           => $variant->sku,
            'name'          => $variant->name,
            'is_active'     => $variant->is_active,
            'sort_order'    => $variant->sort_order,
            'signature'     => $variant->signature,
            'meta'          => $variant->meta,
            'option_values' => $variant->optionValues->map(fn ($ov) => [
                'id'         => $ov->id,
                'name'       => $ov->name,
                'code'       => $ov->code,
                'is_active'  => $ov->is_active,
                'sort_order' => $ov->sort_order,
                'meta'       => $ov->meta,
            ])->all(),
            'prices'      => $variant->prices->map(fn ($p) => $this->priceToArray($p))->all(),
            'price_tiers' => $variant->priceTiers->map(fn ($t) => $this->priceTierToArray($t))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addonGroupToArray(object $group): array
    {
        return [
            'id'                   => $group->id,
            'code'                 => $group->code,
            'name'                 => $group->name,
            'selection_type'       => $group->selection_type?->value,
            'min_selected'         => $group->min_selected,
            'max_selected'         => $group->max_selected,
            'free_selection_limit' => $group->free_selection_limit,
            'is_auto_applied'      => $group->is_auto_applied,
            'meta'                 => $group->meta,
            'items'                => $group->items->map(fn ($item) => [
                'id'               => $item->id,
                'addon_code'       => $item->addon->code,
                'addon_name'       => $item->addon->name,
                'default_selected' => $item->default_selected,
                'is_active'        => $item->is_active,
                'is_free_eligible' => $item->is_free_eligible,
                'sort_order'       => $item->sort_order,
                'meta'             => $item->meta,
                'modifiers'        => $item->modifiers->map(fn ($m) => [
                    'id'                 => $m->id,
                    'modifier_type'      => $m->modifier_type?->value,
                    'applies_to'         => $m->applies_to?->value,
                    'percent'            => $m->percent,
                    'rounding_mode'      => $m->rounding_mode?->value,
                    'product_variant_id' => $m->product_variant_id,
                    'min_qty'            => $m->min_qty,
                    'max_qty'            => $m->max_qty,
                    'meta'               => $m->meta,
                    'prices'             => $m->prices->map(fn ($p) => $this->priceToArray($p))->all(),
                    'price_tiers'        => $m->priceTiers->map(fn ($t) => $this->priceTierToArray($t))->all(),
                ])->all(),
            ])->all(),
        ];
    }
}
