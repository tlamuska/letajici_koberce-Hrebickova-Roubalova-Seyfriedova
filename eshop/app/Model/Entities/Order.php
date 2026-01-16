<?php

namespace App\Model\Entities;

use DateTimeImmutable;
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
 * @property DateTimeImmutable|null $updatedAt
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

    public const SHIPPING_PRICES = [
        'courier' => 90.0,
        'pickup'  => 0.0,
        'post'    => 60.0,
    ];

    public const SHIPPING_LABELS = [
        'courier' => 'Kurýr',
        'pickup'  => 'Osobní odběr',
        'post'    => 'Pošta',
    ];

    public const PAYMENT_LABELS = [
        'bank' => 'Převod na účet',
        'cod'  => 'Dobírka',
    ];

    public static function shippingPrice(string $method): float
    {
        return (float) (self::SHIPPING_PRICES[$method] ?? 0.0);
    }

    public static function getShippingLabels(): array
    {
        return self::SHIPPING_LABELS;
    }
    public static function shippingLabel(string $method): string
    {
        return self::SHIPPING_LABELS[$method] ?? $method;
    }

    public static function getPaymentLabels(): array
    {
        return self::PAYMENT_LABELS;
    }
    public static function paymentLabel(string $method): string
    {
        return self::PAYMENT_LABELS[$method] ?? $method;
    }

    public static function getStatusLabels(): array
    {
        return self::STATUS_LABELS;
    }
    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function getShippingLabelsWithPrice(): array
    {
        $out = [];
        foreach (self::SHIPPING_LABELS as $key => $label) {
            $price = (float) (self::SHIPPING_PRICES[$key] ?? 0.0);
            $out[$key] = sprintf('%s (%.0f Kč)', $label, $price);
        }
        return $out;
    }

    /**
     * gettery pro Latte: {$order->statusLabel} atd.
     */
    public function getStatusLabel(): string
    {
        return self::statusLabel((string) $this->status);
    }
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->row->updated_at;
    }

    public function getPaymentLabel(): string
    {
        return self::paymentLabel((string) $this->paymentMethod);
    }

    public function getShippingLabel(): string
    {
        return self::shippingLabel((string) $this->shippingMethod);
    }



}