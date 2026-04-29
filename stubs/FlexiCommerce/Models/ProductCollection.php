<?php

namespace Daikazu\FlexiCommerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @property string $slug
 * @property string $name
 * @property \Daikazu\FlexiCommerce\Enums\ProductStatus|null $type
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $parent_id
 * @property array<string, mixed>|null $meta
 * @property Collection<int, object> $products
 * @property Collection<int, object> $children
 * @method static \Daikazu\FlexiCommerce\Models\ProductCollectionBuilder query()
 * @method bool relationLoaded(string $relation)
 */
class ProductCollection extends Model
{
}

/**
 * @extends Builder<ProductCollection>
 */
class ProductCollectionBuilder extends Builder
{
    /**
     * @param  int  $perPage
     * @param  array<int, string>  $columns
     * @param  string  $pageName
     * @param  int  $page
     * @return LengthAwarePaginator<int, ProductCollection>
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        return parent::paginate($perPage, $columns, $pageName, $page);
    }
}
