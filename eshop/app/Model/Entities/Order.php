<?php

namespace App\Model\Entities;

use Dibi\DateTime;
use LeanMapper\Entity;

/**
 * Class Order
 * @package App\Model\Entities
 *
 * @property int $orderId
 * @property int $userId
 * @property int $cartId
 * @property string $status
 * @property string $customerName
 * @property string $customerEmail
 * @property string|null $phone
 * @property string $deliveryAddress
 * @property string|null $note
 * @property float $itemsTotal
 * @property string $shippingMethod
 * @property float $shippingPrice
 * @property string $paymentMethod
 * @property float $grandTotal
 * @property DateTime|null $createdAt
 *
 * @property OrderItem[] $items m:belongsToMany
 */

class Order extends Entity implements \Nette\Security\Resource
{

    function getResourceId(): string
    {
        return 'Order';
    }

    public const STATUS_LABELS = [
        'new' => 'Nová',
        'paid' => 'Zaplacená',
        'processing' => 'Zpracovává se',
        'shipped' => 'Odeslaná',
        'delivered' => 'Doručená',
        'cancelled' => 'Zrušená',
    ];

    public static function getStatusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    // getter pro Latte: {$order->statusLabel}
    public function getStatusLabel(): string
    {
        return self::statusLabel((string) $this->status);
    }


}