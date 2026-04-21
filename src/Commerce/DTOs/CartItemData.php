<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Commerce\DTOs;

use Daikazu\Flexicart\Conditions\Condition;

final readonly class CartItemData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public float $price,
        public int $quantity,
        public array $attributes,
        public array $conditions,
        public array $raw,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        /** @var array<int, array<string, mixed>> $conditions */
        $conditions = is_array($data['conditions'] ?? null) ? $data['conditions'] : [];

        $idRaw = $data['id'] ?? '';
        $nameRaw = $data['name'] ?? '';
        $priceRaw = $data['price'] ?? 0.0;
        $quantityRaw = $data['quantity'] ?? 0;

        return new self(
            id: is_string($idRaw) ? $idRaw : '',
            name: is_string($nameRaw) ? $nameRaw : '',
            price: is_float($priceRaw) ? $priceRaw : (is_int($priceRaw) ? (float) $priceRaw : (is_numeric($priceRaw) ? (float) $priceRaw : 0.0)),
            quantity: is_int($quantityRaw) ? $quantityRaw : (is_numeric($quantityRaw) ? (int) $quantityRaw : 0),
            attributes: $attributes,
            conditions: $conditions,
            raw: $data,
        );
    }

    /**
     * Convert to an array compatible with Cart::addItem().
     *
     * @return array<string, mixed>
     */
    public function toCartArray(): array
    {
        $conditions = array_map(
            fn (array $c) => Condition::fromArray($c),
            $this->conditions,
        );

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'price'      => $this->price,
            'quantity'   => $this->quantity,
            'attributes' => $this->attributes,
            'conditions' => $conditions,
        ];
    }
}
