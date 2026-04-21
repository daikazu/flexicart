<?php

namespace Daikazu\FlexiCommerce\Actions\Pricing;

/**
 * @phpstan-type AddonSelection array<string, mixed>
 */
class ResolvePriceAction
{
    /**
     * @param  array<int, AddonSelection>  $addonSelections
     * @return array<string, mixed>
     */
    public function handle(
        object $product,
        ?int $variantId = null,
        int $quantity = 1,
        string $currency = 'USD',
        array $addonSelections = [],
    ): array {
        return [];
    }
}
