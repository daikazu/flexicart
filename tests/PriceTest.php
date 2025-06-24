<?php

declare(strict_types=1);

use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;

describe('Price', function (): void {
    beforeEach(function (): void {
        // Set default currency and locale for testing
        config(['flexicart.currency' => 'USD']);
        config(['flexicart.locale' => 'en_US']);
    });

    describe('Constructor', function (): void {
        test('can be instantiated with integer', function (): void {
            $price = new Price(100);
            expect($price)->toBeInstanceOf(Price::class)
                ->and($price->formatted())->toBe('$100.00');
        });

        test('can be instantiated with float', function (): void {
            $price = new Price(99.99);
            expect($price)->toBeInstanceOf(Price::class)
                ->and($price->formatted())->toBe('$99.99');
        });

        test('can be instantiated with string', function (): void {
            $price = new Price('50.25');
            expect($price)->toBeInstanceOf(Price::class)
                ->and($price->formatted())->toBe('$50.25');
        });

        test('can be instantiated with Money object', function (): void {
            $money = Money::of(75.50, 'USD');
            $price = new Price($money);
            expect($price)->toBeInstanceOf(Price::class)
                ->and($price->formatted())->toBe('$75.50');
        });

        test('throws exception with invalid value', function (): void {
            expect(fn () => new Price('invalid'))
                ->toThrow(PriceException::class);
        });
    });

    describe('Static Factory Methods', function (): void {
        test('can create from static method', function (): void {
            $price = Price::from(100);
            expect($price)->toBeInstanceOf(Price::class)
                ->and($price->formatted())->toBe('$100.00');
        });

        test('can create zero price', function (): void {
            $price = Price::zero();
            expect($price)->toBeInstanceOf(Price::class)
                ->and($price->formatted())->toBe('$0.00');
        });
    });

    describe('Value Retrieval', function (): void {
        test('can get minor value', function (): void {
            $price = new Price(10.50);
            expect($price->getMinorValue())->toBe(1050);
        });

        test('can convert to rational money', function (): void {
            $price = new Price(10.50);
            expect($price->toRational())->toBeInstanceOf(RationalMoney::class);
        });
    });

    describe('Arithmetic Operations', function (): void {
        test('can add another price', function (): void {
            $price1 = new Price(100);
            $price2 = new Price(50);
            $result = $price1->plus($price2);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$150.00');
        });

        test('can add numeric value', function (): void {
            $price = new Price(100);
            $result = $price->plus(25);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$125.00');
        });

        test('can subtract another price', function (): void {
            $price1 = new Price(100);
            $price2 = new Price(30);
            $result = $price1->subtract($price2);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$70.00');
        });

        test('can subtract numeric value', function (): void {
            $price = new Price(100);
            $result = $price->subtract(25);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$75.00');
        });

        test('prevents negative price when subtracting', function (): void {
            $price1 = new Price(50);
            $price2 = new Price(100);
            $result = $price1->subtract($price2);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$0.00');
        });

        test('can multiply by factor', function (): void {
            $price = new Price(10);
            $result = $price->multiplyBy(3);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$30.00');
        });

        test('can apply percentage increase', function (): void {
            $price = new Price(100);
            $result = $price->percentage(10); // 10% increase

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$110.00');
        });

        test('can apply percentage decrease', function (): void {
            $price = new Price(100);
            $result = $price->percentage(-10); // 10% decrease

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$90.00');
        });

        test('can divide by a divisor', function (): void {
            $price = new Price(100);
            $result = $price->divideBy(4);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$25.00');
        });

        test('can divide by a decimal divisor', function (): void {
            $price = new Price(100);
            $result = $price->divideBy(2.5);

            expect($result)->toBeInstanceOf(Price::class)
                ->and($result->formatted())->toBe('$40.00');
        });

        test('throws exception when dividing by zero', function (): void {
            $price = new Price(100);

            expect(fn () => $price->divideBy(0))
                ->toThrow(\InvalidArgumentException::class, 'Cannot divide by zero');
        });
    });

    describe('Formatting', function (): void {
        test('can format with default locale', function (): void {
            $price = new Price(1234.56);
            expect($price->formatted())->toBe('$1,234.56');
        });

        test('respects custom locale', function (): void {
            config(['flexicart.locale' => 'de_DE']);
            $price = new Price(1234.56);
            $formatted = $price->formatted();

            // Check for German locale formatting characteristics
            expect($formatted)->toContain('1.234')  // Thousands separator as period
                ->and($formatted)->toContain(',56'); // Decimal separator as comma
        });

        test('can be cast to string', function (): void {
            $price = new Price(99.99);
            expect((string) $price)->toBe('$99.99');
        });
    });

    describe('Currency Handling', function (): void {
        test('respects custom currency', function (): void {
            config(['flexicart.currency' => 'EUR']);
            $price = new Price(100);
            expect($price->formatted())->toContain('€');
        });

        test('throws exception with invalid currency', function (): void {
            config(['flexicart.currency' => 'INVALID']);
            expect(fn () => new Price(100))
                ->toThrow(PriceException::class);
        });
    });

});
