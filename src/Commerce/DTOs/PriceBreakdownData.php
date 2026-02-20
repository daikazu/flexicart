<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

final readonly class PriceBreakdownData
{
    /**
     * @param  array{id: int, sku: string, name: string}|null  $variant
     * @param  array{min_qty: int, max_qty: int|null}|null  $tierApplied
     * @param  array<int, array<string, mixed>>  $addons
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $productSlug,
        public ?array $variant,
        public int $quantity,
        public string $currency,
        public string $unitPrice,
        public ?array $tierApplied,
        public array $addons,
        public string $lineTotal,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            productSlug: $data['product_slug'],
            variant: $data['variant'],
            quantity: $data['quantity'],
            currency: $data['currency'],
            unitPrice: $data['unit_price'],
            tierApplied: $data['tier_applied'],
            addons: $data['addons'],
            lineTotal: $data['line_total'],
            raw: $data,
        );
    }
}
