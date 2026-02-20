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
use InvalidArgumentException;

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
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $page = (int) ($filters['page'] ?? 1);

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

    /**
     * Get a single collection by slug.
     *
     * @throws CommerceConnectionException
     */
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

    /**
     * Resolve price for a configured product.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws CommerceConnectionException
     */
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
        } catch (InvalidArgumentException $e) {
            throw new CommerceConnectionException($e->getMessage(), 0, $e);
        }

        return PriceBreakdownData::fromArray($result);
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
        } catch (InvalidArgumentException $e) {
            throw new CommerceConnectionException($e->getMessage(), 0, $e);
        }

        return CartItemData::fromArray($this->toCartItem($result, $product));
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
        $data = $this->cartItem($slug, $config);

        $cart ??= app(CartInterface::class);
        $cart->addItem($data->toCartArray());

        return $cart->item($data->id)
            ?? throw new CommerceConnectionException("Failed to add item '{$data->id}' to cart.");
    }

    /**
     * @return object The Product model instance
     */
    private function findActiveProduct(string $slug): object
    {
        $product = \Daikazu\FlexiCommerce\Models\Product::query()
            ->where('slug', $slug)
            ->where('status', \Daikazu\FlexiCommerce\Enums\ProductStatus::Active)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.optionValues.option',
            ])
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

        // Build option_values map from variant (pre-loaded by findActiveProduct)
        $optionValues = [];
        if ($result['variant'] !== null) {
            $variant = $product->variants->firstWhere('id', $result['variant']['id']);

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
                'name'       => "Addon: {$addon['name']}",
                'value'      => $value,
                'type'       => 'fixed',
                'target'     => $appliesTo,
                'attributes' => [
                    'addon_code'  => $addon['addon_code'],
                    'group_code'  => $addon['group_code'],
                    'modifier_id' => $addon['modifier_id'],
                ],
                'order'   => $i,
                'taxable' => true,
            ];
        }

        return [
            'id'         => $cartId,
            'name'       => $name,
            'price'      => (float) $result['unit_price'],
            'quantity'   => $result['quantity'],
            'attributes' => [
                'product_slug'  => $result['product_slug'],
                'variant_id'    => $result['variant']['id'] ?? null,
                'sku'           => $sku,
                'option_values' => $optionValues,
                'source'        => 'flexi-commerce',
                'resolved_at'   => now()->toIso8601String(),
            ],
            'conditions' => $conditions,
        ];
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
            'pivot'                => $group->pivot ? [
                'label'                         => $group->pivot->label,
                'selection_type_override'       => $group->pivot->selection_type_override,
                'min_selected_override'         => $group->pivot->min_selected_override,
                'max_selected_override'         => $group->pivot->max_selected_override,
                'free_selection_limit_override' => $group->pivot->free_selection_limit_override,
                'sort_order'                    => $group->pivot->sort_order,
                'is_active'                     => $group->pivot->is_active,
            ] : null,
            'items' => $group->items->map(fn ($item) => [
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

    /**
     * @return array<string, mixed>
     */
    private function collectionToArray(object $collection): array
    {
        return [
            'slug'        => $collection->slug,
            'name'        => $collection->name,
            'type'        => $collection->type?->value,
            'description' => $collection->description,
            'is_active'   => $collection->is_active,
            'parent_id'   => $collection->parent_id,
            'meta'        => $collection->meta ?? [],
            'products'    => $collection->relationLoaded('products')
                ? $collection->products->map(fn ($p) => $this->productListToArray($p))->all()
                : [],
            'children' => $collection->relationLoaded('children')
                ? $collection->children->map(fn ($c) => $this->collectionToArray($c))->all()
                : [],
        ];
    }
}
