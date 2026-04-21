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
use Daikazu\Flexicart\Enums\AddItemBehavior;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class LocalCommerceDriver implements CommerceClientInterface
{
    private ?object $store = null;

    private bool $storeResolved = false;

    public function __construct(
        private readonly ?string $storeId = null,
    ) {}

    /**
     * List active products (paginated).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ProductData>
     *
     * @throws CommerceConnectionException
     */
    public function products(array $filters = []): LengthAwarePaginator
    {
        $perPageRaw = $filters['per_page'] ?? 15;
        $pageRaw = $filters['page'] ?? 1;
        $perPage = min(is_numeric($perPageRaw) ? (int) $perPageRaw : 15, 100);
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;

        $query = \Daikazu\FlexiCommerce\Models\Product::query()
            ->where('status', \Daikazu\FlexiCommerce\Enums\ProductStatus::Active)
            ->with(['prices', 'media'])
            ->orderBy('name');

        if ($store = $this->resolveStore()) {
            $query->forStore($store);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var list<ProductData> $items */
        $items = $paginator->getCollection()
            ->map(fn (\Daikazu\FlexiCommerce\Models\Product $product) => ProductData::fromArray($this->productListToArray($product)))
            ->all();

        /** @var LengthAwarePaginator<int, ProductData> */
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
        $query = \Daikazu\FlexiCommerce\Models\Product::query()
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
                'variants.media',
                'addonGroups'       => fn ($q) => $q->wherePivot('is_active', true),
                'addonGroups.items' => fn ($q) => $q->where('is_active', true),
                'addonGroups.items.addon.modifiers.prices',
                'addonGroups.items.addon.modifiers.priceTiers.prices',
                'media',
            ]);

        if ($store = $this->resolveStore()) {
            $query->forStore($store);
        }

        $product = $query->first();

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
     * @return LengthAwarePaginator<int, CollectionData>
     *
     * @throws CommerceConnectionException
     */
    public function collections(array $filters = []): LengthAwarePaginator
    {
        $perPageRaw = $filters['per_page'] ?? 15;
        $pageRaw = $filters['page'] ?? 1;
        $perPage = min(is_numeric($perPageRaw) ? (int) $perPageRaw : 15, 100);
        $page = is_numeric($pageRaw) ? (int) $pageRaw : 1;

        $query = \Daikazu\FlexiCommerce\Models\ProductCollection::query()
            ->where('is_active', true)
            ->with('children')
            ->orderBy('name');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var list<CollectionData> $items */
        $items = $paginator->getCollection()
            ->map(fn (\Daikazu\FlexiCommerce\Models\ProductCollection $collection) => CollectionData::fromArray($this->collectionToArray($collection)))
            ->all();

        /** @var LengthAwarePaginator<int, CollectionData> */
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

        $variantIdRaw = $config['variant_id'] ?? null;
        $quantityRaw = $config['quantity'] ?? 1;
        $currencyRaw = $config['currency'] ?? 'USD';
        /** @var array<int, array<string, mixed>> $addonSelections */
        $addonSelections = is_array($config['addon_selections'] ?? null) ? $config['addon_selections'] : [];

        try {
            /** @var array<string, mixed> $result */
            $result = (new \Daikazu\FlexiCommerce\Actions\Pricing\ResolvePriceAction)->handle(
                product: $product,
                variantId: $variantIdRaw !== null && is_numeric($variantIdRaw) ? (int) $variantIdRaw : null,
                quantity: is_numeric($quantityRaw) ? (int) $quantityRaw : 1,
                currency: strtoupper(is_string($currencyRaw) ? $currencyRaw : 'USD'),
                addonSelections: $addonSelections,
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

        $variantIdRaw = $config['variant_id'] ?? null;
        $quantityRaw = $config['quantity'] ?? 1;
        $currencyRaw = $config['currency'] ?? 'USD';
        /** @var array<int, array<string, mixed>> $addonSelections */
        $addonSelections = is_array($config['addon_selections'] ?? null) ? $config['addon_selections'] : [];

        try {
            /** @var array<string, mixed> $result */
            $result = (new \Daikazu\FlexiCommerce\Actions\Pricing\ResolvePriceAction)->handle(
                product: $product,
                variantId: $variantIdRaw !== null && is_numeric($variantIdRaw) ? (int) $variantIdRaw : null,
                quantity: is_numeric($quantityRaw) ? (int) $quantityRaw : 1,
                currency: strtoupper(is_string($currencyRaw) ? $currencyRaw : 'USD'),
                addonSelections: $addonSelections,
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
     * Resolve the Store model from the configured storeId (cached).
     *
     * @throws CommerceConnectionException
     */
    private function resolveStore(): ?object
    {
        if ($this->storeId === null) {
            return null;
        }

        if ($this->storeResolved) {
            return $this->store;
        }

        $this->storeResolved = true;

        $cacheKey = 'flexicart:local-store:' . $this->storeId;

        /** @var int|false $storePk */
        $storePk = Cache::remember($cacheKey, 60, function (): int | false {
            $key = \Daikazu\FlexiCommerce\Models\Store::query()
                ->where('store_id', $this->storeId)
                ->where('is_active', true)
                ->first()?->getKey() ?? false;

            return is_int($key) ? $key : false;
        });

        if ($storePk === false) {
            throw new CommerceConnectionException(
                "No active store found with store_id '{$this->storeId}'."
            );
        }

        $this->store = \Daikazu\FlexiCommerce\Models\Store::find($storePk);

        if ($this->store === null) {
            Cache::forget($cacheKey);

            throw new CommerceConnectionException(
                "No active store found with store_id '{$this->storeId}'."
            );
        }

        return $this->store;
    }

    /**
     * @return \Daikazu\FlexiCommerce\Models\Product The Product model instance
     */
    private function findActiveProduct(string $slug): \Daikazu\FlexiCommerce\Models\Product
    {
        $query = \Daikazu\FlexiCommerce\Models\Product::query()
            ->where('slug', $slug)
            ->where('status', \Daikazu\FlexiCommerce\Enums\ProductStatus::Active)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.optionValues.option',
            ]);

        if ($store = $this->resolveStore()) {
            $query->forStore($store);
        }

        $product = $query->first();

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
    private function toCartItem(array $result, \Daikazu\FlexiCommerce\Models\Product $product): array
    {
        /** @var array<string, mixed>|null $variantResult */
        $variantResult = is_array($result['variant'] ?? null) ? $result['variant'] : null;
        $skuRaw = $variantResult['sku'] ?? $product->slug;
        $sku = is_string($skuRaw) ? $skuRaw : $product->slug;
        $variantNameRaw = $variantResult['name'] ?? null;
        $variantName = is_string($variantNameRaw) ? $variantNameRaw : null;

        /** @var array<int, array<string, mixed>> $addonsResult */
        $addonsResult = is_array($result['addons'] ?? null) ? $result['addons'] : [];

        $addonParts = collect($addonsResult)
            ->groupBy('group_code')
            ->map(fn ($items, $group) => $group . '=' . $items->pluck('addon_code')->unique()->implode('+'))
            ->implode(':');

        $cartId = $addonParts !== '' ? "{$sku}:{$addonParts}" : $sku;

        $name = $variantName !== null
            ? "{$product->name} - {$variantName}"
            : $product->name;

        // Build option_values map from variant (pre-loaded by findActiveProduct)
        $optionValues = [];
        if ($variantResult !== null) {
            $variantIdRaw = $variantResult['id'] ?? null;
            $variant = is_int($variantIdRaw) ? $product->variants->firstWhere('id', $variantIdRaw) : null;

            if ($variant !== null) {
                /** @var iterable<int, object> $optionValuesRelation */
                $optionValuesRelation = $variant->optionValues ?? [];
                foreach ($optionValuesRelation as $ov) {
                    /** @var string $optionCode */
                    $optionCode = $ov->option->code ?? '';
                    $optionValues[$optionCode] = $ov->name;
                }
            }
        }

        // Build conditions from addon modifiers
        $conditions = [];
        foreach ($addonsResult as $i => $addon) {
            if ($addon['is_free'] ?? false) {
                continue;
            }

            $unitAmountRaw = $addon['unit_amount'] ?? 0;
            $value = is_numeric($unitAmountRaw) ? (float) $unitAmountRaw : 0.0;
            if ($value == 0.0) {
                continue;
            }

            $appliesToRaw = $addon['applies_to'] ?? '';
            $appliesTo = $appliesToRaw === \Daikazu\FlexiCommerce\Enums\ModifierAppliesTo::Line->value
                ? 'subtotal'
                : 'item';

            $addonNameRaw = $addon['name'] ?? '';
            $conditions[] = [
                'name'       => "Addon: " . (is_string($addonNameRaw) ? $addonNameRaw : ''),
                'value'      => $value,
                'type'       => 'fixed',
                'target'     => $appliesTo,
                'attributes' => [
                    'addon_code'  => $addon['addon_code'] ?? null,
                    'group_code'  => $addon['group_code'] ?? null,
                    'modifier_id' => $addon['modifier_id'] ?? null,
                ],
                'order'   => $i,
                'taxable' => true,
            ];
        }

        $unitPriceRaw = $result['unit_price'] ?? 0;
        $quantityRaw = $result['quantity'] ?? 1;
        $productSlugRaw = $result['product_slug'] ?? '';
        $variantIdForAttr = $variantResult !== null ? ($variantResult['id'] ?? null) : null;

        return [
            'id'         => $cartId,
            'name'       => $name,
            'price'      => is_numeric($unitPriceRaw) ? (float) $unitPriceRaw : 0.0,
            'quantity'   => is_numeric($quantityRaw) ? (int) $quantityRaw : 1,
            'attributes' => [
                'product_slug'  => is_string($productSlugRaw) ? $productSlugRaw : '',
                'variant_id'    => $variantIdForAttr,
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
    private function productListToArray(\Daikazu\FlexiCommerce\Models\Product $product): array
    {
        return [
            'slug'        => $product->slug,
            'name'        => $product->name,
            'description' => $product->description,
            'status'      => $product->status?->value,
            'type'        => $product->type?->value,
            'meta'        => $product->meta ?? [],
            'image_urls'  => method_exists($product, 'getMedia')
                ? $product->getMedia('images')->map(fn ($m) => $m->getUrl())->all()
                : [],
            'prices' => $product->prices->map(fn ($p) => $this->priceToArray($p))->all(),
        ];
    }

    /**
     * Convert a product model to a full detail array with all nested relations.
     *
     * @return array<string, mixed>
     */
    private function productToArray(\Daikazu\FlexiCommerce\Models\Product $product): array
    {
        $imageUrls = [];
        $digitalAssets = [];

        if (method_exists($product, 'getMedia')) {
            $imageUrls = $product->getMedia('images')->map(fn ($m) => $m->getUrl())->all();
            $digitalAssets = $product->getMedia('documents')->map(fn ($m) => [
                'name'      => $m->name,
                'file_name' => $m->file_name,
                'mime_type' => $m->mime_type,
                'size'      => $m->size,
                'url'       => $m->getUrl(),
            ])->all();
        }

        return [
            'slug'           => $product->slug,
            'name'           => $product->name,
            'description'    => $product->description,
            'status'         => $product->status?->value,
            'type'           => $product->type?->value,
            'meta'           => $product->meta ?? [],
            'image_urls'     => $imageUrls,
            'digital_assets' => $digitalAssets,
            'prices'         => $product->prices->map(fn ($p) => $this->priceToArray($p))->all(),
            'price_tiers'    => $product->priceTiers->map(fn ($t) => $this->priceTierToArray($t))->all(),
            'options'        => $product->options->map(fn ($o) => $this->optionToArray($o))->all(),
            'variants'       => $product->variants->map(fn ($v) => $this->variantToArray($v))->all(),
            'addon_groups'   => $product->addonGroups->map(fn ($g) => $this->addonGroupToArray($g))->all(),
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
            'image_urls' => method_exists($variant, 'getMedia')
                ? $variant->getMedia('images')->map(fn ($m) => $m->getUrl())->all()
                : [],
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
                'modifiers'        => $item->addon->modifiers->map(fn ($m) => [
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
    private function collectionToArray(\Daikazu\FlexiCommerce\Models\ProductCollection $collection): array
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
