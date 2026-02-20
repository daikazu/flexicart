<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Conditions\Rules;

use Brick\Math\RoundingMode;
use Daikazu\Flexicart\Enums\ConditionType;
use Daikazu\Flexicart\Exceptions\PriceException;
use Daikazu\Flexicart\Price;
use Illuminate\Support\Fluent;

/**
 * Tiered Rule - Progressive discounts based on subtotal thresholds.
 *
 * Example:
 *   $100+ = 5% off
 *   $200+ = 10% off
 *   $500+ = 15% off
 *
 * Only the highest applicable tier is applied (not cumulative).
 */
final class TieredRule extends AbstractRule
{
    public ConditionType $type = ConditionType::PERCENTAGE;

    /**
     * @param  string  $name  Rule name
     * @param  array<float, float>  $tiers  Map of threshold => discount percentage (e.g., [100 => 5, 200 => 10])
     * @param  array<string, mixed>|Fluent<string, mixed>  $attributes
     */
    public function __construct(
        string $name,
        public readonly array $tiers,
        array | Fluent $attributes = [],
        int $order = 0,
        bool $taxable = false
    ) {
        parent::__construct($name, 0, $attributes, $order, $taxable);
    }

    public function applies(): bool
    {
        return $this->getApplicableTier() !== null;
    }

    /**
     * @throws PriceException
     */
    public function getDiscount(): Price
    {
        $tier = $this->getApplicableTier();

        if ($tier === null) {
            return Price::zero();
        }

        $discountPercent = abs($tier['discount']);
        $discountAmount = $this->subtotal->multiplyBy($discountPercent / 100, RoundingMode::HALF_UP);

        // Return negative for discount
        return new Price(-$discountAmount->toFloat());
    }

    public function formattedValue(): string
    {
        $tier = $this->getApplicableTier();

        if ($tier === null) {
            return 'No discount';
        }

        return sprintf('%s%% off', abs($tier['discount']));
    }

    /**
     * Get the highest applicable tier based on current subtotal.
     *
     * @return array{threshold: float, discount: float}|null
     */
    private function getApplicableTier(): ?array
    {
        $subtotalAmount = $this->subtotal->toFloat();
        $applicableTier = null;

        // Sort tiers by threshold descending to find highest applicable
        $sortedTiers = $this->tiers;
        krsort($sortedTiers);

        foreach ($sortedTiers as $threshold => $discount) {
            if ($subtotalAmount >= (float) $threshold) {
                $applicableTier = [
                    'threshold' => (float) $threshold,
                    'discount'  => (float) $discount,
                ];
                break;
            }
        }

        return $applicableTier;
    }

    /**
     * Get all tiers for display purposes.
     *
     * @return array<array{threshold: float, discount: float}>
     */
    public function getTiers(): array
    {
        $result = [];

        foreach ($this->tiers as $threshold => $discount) {
            $result[] = [
                'threshold' => (float) $threshold,
                'discount'  => (float) $discount,
            ];
        }

        return $result;
    }
}
