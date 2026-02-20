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
        return new self(
            id: $data['id'],
            name: $data['name'],
            price: (float) $data['price'],
            quantity: (int) $data['quantity'],
            attributes: $data['attributes'] ?? [],
            conditions: $data['conditions'] ?? [],
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
