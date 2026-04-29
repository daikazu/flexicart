<?php

namespace Daikazu\FlexiCommerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $store_id
 * @property bool $is_active
 * @method static \Daikazu\FlexiCommerce\Models\StoreBuilder query()
 * @method static Store|null find(mixed $id)
 */
class Store extends Model
{
}

/**
 * @extends Builder<Store>
 */
class StoreBuilder extends Builder
{
}
