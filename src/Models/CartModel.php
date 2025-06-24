<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CartModel extends Model
{
    protected $table = 'carts';

    protected $fillable = [
        'user_id',
        'session_id',
        'conditions',
        'created_at',
        'updated_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        self::deleting(function ($cart) {
            $cart->items()->delete();
        });

    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItemModel::class, 'cart_id');
    }

    protected function casts(): array
    {
        return [
            'conditions' => 'collection',
        ];
    }
}
