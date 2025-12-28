# Blade Templates

Examples of displaying cart data in Laravel Blade templates.

## Basic Cart Display

```blade
{{-- resources/views/cart.blade.php --}}
@if(Cart::isEmpty())
    <p>Your cart is empty.</p>
@else
    <div class="cart">
        <h2>Shopping Cart</h2>

        <div class="cart-items">
            @foreach(Cart::items() as $item)
                <div class="cart-item">
                    <h4>{{ $item->name }}</h4>
                    <p>Price: {{ $item->unitPrice()->formatted() }}</p>
                    <p>Quantity: {{ $item->quantity }}</p>
                    <p>Subtotal: {{ $item->subtotal()->formatted() }}</p>

                    @if($item->attributes)
                        <div class="attributes">
                            @foreach($item->attributes as $key => $value)
                                <span class="attribute">{{ $key }}: {{ $value }}</span>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('cart.remove') }}" method="POST">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        <button type="submit">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="cart-totals">
            <p>Subtotal: {{ Cart::subtotal()->formatted() }}</p>
            <p><strong>Total: {{ Cart::total()->formatted() }}</strong></p>
        </div>

        <div class="cart-actions">
            <a href="{{ route('cart.clear') }}" class="btn btn-secondary">Clear Cart</a>
            <a href="{{ route('checkout') }}" class="btn btn-primary">Checkout</a>
        </div>
    </div>
@endif
```

## Cart Summary Component

```blade
{{-- resources/views/components/cart-summary.blade.php --}}
<div class="cart-summary">
    <span class="cart-count">{{ Cart::count() }} items</span>
    <span class="cart-total">{{ Cart::total()->formatted() }}</span>
</div>
```

## Cart with Conditions

```blade
{{-- resources/views/cart-with-discounts.blade.php --}}
<div class="cart-totals">
    <div class="subtotal">
        <span>Subtotal:</span>
        <span>{{ Cart::subtotal()->formatted() }}</span>
    </div>

    @foreach(Cart::conditions() as $condition)
        <div class="condition {{ $condition->value < 0 ? 'discount' : 'fee' }}">
            <span>{{ $condition->name }}:</span>
            <span>{{ $condition->formattedValue() }}</span>
        </div>
    @endforeach

    <div class="total">
        <span><strong>Total:</strong></span>
        <span><strong>{{ Cart::total()->formatted() }}</strong></span>
    </div>
</div>
```

## Item with Conditions

```blade
@foreach(Cart::items() as $item)
    <div class="cart-item">
        <h4>{{ $item->name }}</h4>
        <p>Unit Price: {{ $item->unitPrice()->formatted() }}</p>
        <p>Quantity: {{ $item->quantity }}</p>

        @if($item->conditions()->isNotEmpty())
            <div class="item-conditions">
                @foreach($item->conditions() as $condition)
                    <span class="badge">{{ $condition->name }}: {{ $condition->formattedValue() }}</span>
                @endforeach
            </div>
            <p>Adjusted Total: {{ $item->total()->formatted() }}</p>
        @else
            <p>Item Total: {{ $item->subtotal()->formatted() }}</p>
        @endif
    </div>
@endforeach
```

## Quantity Update Form

```blade
<form action="{{ route('cart.update') }}" method="POST">
    @csrf
    @method('PATCH')
    <input type="hidden" name="item_id" value="{{ $item->id }}">
    <input type="number"
           name="quantity"
           value="{{ $item->quantity }}"
           min="1"
           max="99"
           class="quantity-input">
    <button type="submit">Update</button>
</form>
```

## Tax Display

```blade
<div class="cart-totals">
    <div class="subtotal">
        <span>Subtotal:</span>
        <span>{{ Cart::subtotal()->formatted() }}</span>
    </div>

    <div class="taxable-subtotal">
        <span>Taxable Amount:</span>
        <span>{{ Cart::getTaxableSubtotal()->formatted() }}</span>
    </div>

    {{-- Display tax conditions --}}
    @foreach(Cart::conditions()->filter(fn($c) => $c->taxable) as $tax)
        <div class="tax-line">
            <span>{{ $tax->name }}:</span>
            <span>{{ $tax->formattedValue() }}</span>
        </div>
    @endforeach

    <div class="total">
        <span><strong>Total:</strong></span>
        <span><strong>{{ Cart::total()->formatted() }}</strong></span>
    </div>
</div>
```

## Mini Cart (Header)

```blade
{{-- resources/views/components/mini-cart.blade.php --}}
<div class="mini-cart" x-data="{ open: false }">
    <button @click="open = !open" class="cart-toggle">
        <svg>...</svg>
        @if(Cart::count() > 0)
            <span class="badge">{{ Cart::uniqueCount() }}</span>
        @endif
    </button>

    <div x-show="open" class="mini-cart-dropdown">
        @if(Cart::isEmpty())
            <p>Your cart is empty</p>
        @else
            @foreach(Cart::items()->take(3) as $item)
                <div class="mini-cart-item">
                    <span>{{ $item->name }}</span>
                    <span>{{ $item->quantity }} x {{ $item->unitPrice()->formatted() }}</span>
                </div>
            @endforeach

            @if(Cart::uniqueCount() > 3)
                <p class="more-items">+ {{ Cart::uniqueCount() - 3 }} more items</p>
            @endif

            <div class="mini-cart-total">
                <strong>Total: {{ Cart::total()->formatted() }}</strong>
            </div>

            <a href="{{ route('cart.index') }}" class="view-cart-btn">View Cart</a>
        @endif
    </div>
</div>
```

## Empty Cart State

```blade
@if(Cart::isEmpty())
    <div class="empty-cart">
        <svg class="empty-cart-icon">...</svg>
        <h3>Your cart is empty</h3>
        <p>Looks like you haven't added anything to your cart yet.</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary">
            Continue Shopping
        </a>
    </div>
@endif
```

## Route Examples

```php
// routes/web.php
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add', [CartController::class, 'add'])->name('add');
    Route::patch('/update', [CartController::class, 'update'])->name('update');
    Route::delete('/remove', [CartController::class, 'remove'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
});
```

## Controller Example

```php
<?php

namespace App\Http\Controllers;

use Daikazu\Flexicart\Facades\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        return view('cart.index');
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required',
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'integer|min:1',
            'attributes' => 'array',
        ]);

        Cart::addItem($validated);

        return back()->with('success', 'Item added to cart!');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required',
            'quantity' => 'required|integer|min:1',
        ]);

        Cart::updateItem($validated['item_id'], [
            'quantity' => $validated['quantity'],
        ]);

        return back()->with('success', 'Cart updated!');
    }

    public function remove(Request $request)
    {
        Cart::removeItem($request->input('item_id'));

        return back()->with('success', 'Item removed from cart!');
    }

    public function clear()
    {
        Cart::clear();

        return back()->with('success', 'Cart cleared!');
    }
}
```
