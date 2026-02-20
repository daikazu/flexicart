<?php

declare(strict_types=1);

namespace Daikazu\Flexicart\Events;

use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CartEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The cart ID associated with this event.
     */
    public readonly string $cartId;

    /**
     * The timestamp when the event occurred.
     */
    public readonly DateTimeImmutable $occurredAt;

    public function __construct(string $cartId)
    {
        $this->cartId = $cartId;
        $this->occurredAt = new DateTimeImmutable;
    }
}
