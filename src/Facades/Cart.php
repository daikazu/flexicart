<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Daikazu\Flexicart\Cart
 */
final class Cart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cart';
    }
}
