<?php

namespace Daikazu\FlexiCommerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property \Daikazu\FlexiCommerce\Enums\ProductStatus|null $status
 * @property \Daikazu\FlexiCommerce\Enums\ProductStatus|null $type
 * @property array<string, mixed>|null $meta
 * @property Collection<int, object> $prices
 * @property Collection<int, object> $priceTiers
 * @property Collection<int, object> $options
 * @property Collection<int, object> $variants
 * @property Collection<int, object> $addonGroups
 * @method static \Daikazu\FlexiCommerce\Models\ProductBuilder query()
 * @method static Product|null find(mixed $id)
 * @method bool relationLoaded(string $relation)
 * @method \Illuminate\Support\Collection<int, object> getMedia(string $collection)
 */
class Product extends Model
{
}

/**
 * @extends Builder<Product>
 */
class ProductBuilder extends Builder
{
    public function forStore(object $store): static
    {
        return $this;
    }

    /**
     * @param  int  $perPage
     * @param  array<int, string>  $columns
     * @param  string  $pageName
     * @param  int  $page
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        return parent::paginate($perPage, $columns, $pageName, $page);
    }
}
