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
        $variantRaw = $data['variant'] ?? null;
        /** @var array{id: int, sku: string, name: string}|null $variant */
        $variant = is_array($variantRaw) ? $variantRaw : null;

        $tierAppliedRaw = $data['tier_applied'] ?? null;
        /** @var array{min_qty: int, max_qty: int|null}|null $tierApplied */
        $tierApplied = is_array($tierAppliedRaw) ? $tierAppliedRaw : null;

        /** @var array<int, array<string, mixed>> $addons */
        $addons = is_array($data['addons'] ?? null) ? $data['addons'] : [];

        $productSlugRaw = $data['product_slug'] ?? '';
        $quantityRaw = $data['quantity'] ?? 0;
        $currencyRaw = $data['currency'] ?? '';
        $unitPriceRaw = $data['unit_price'] ?? '';
        $lineTotalRaw = $data['line_total'] ?? '';

        return new self(
            productSlug: is_string($productSlugRaw) ? $productSlugRaw : '',
            variant: $variant,
            quantity: is_int($quantityRaw) ? $quantityRaw : (is_numeric($quantityRaw) ? (int) $quantityRaw : 0),
            currency: is_string($currencyRaw) ? $currencyRaw : '',
            unitPrice: is_string($unitPriceRaw) ? $unitPriceRaw : '',
            tierApplied: $tierApplied,
            addons: $addons,
            lineTotal: is_string($lineTotalRaw) ? $lineTotalRaw : '',
            raw: $data,
        );
    }
}
