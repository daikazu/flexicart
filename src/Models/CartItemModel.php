<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $cart_id
 * @property string $item_id
 * @property string $name
 * @property float $price
 * @property int $quantity
 * @property array<string, mixed> $attributes
 * @property array<int, mixed> $conditions
 */
final class CartItemModel extends Model
{
    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'item_id',
        'name',
        'price',
        'quantity',
        'attributes',
        'conditions',
    ];

    /**
     * @return BelongsTo<CartModel, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(CartModel::class, 'cart_id');
    }

    protected function casts(): array
    {
        return [
            'price'      => 'float',
            'quantity'   => 'integer',
            'attributes' => 'array',
            'conditions' => 'array',
        ];
    }
}
