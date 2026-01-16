<?php

namespace App\Model\Facades;

use App\Model\Entities\Cart;
use App\Model\Entities\Order;
use App\Model\Entities\OrderItem;
use App\Model\Repositories\OrderItemRepository;
use App\Model\Repositories\OrderRepository;
use LeanMapper\Connection;

/**
 * Class OrdersFacade
 * @package App\Model\Facades
 */
class OrdersFacade{
    private OrderRepository $orderRepository;
    private OrderItemRepository $orderItemRepository;
    private Connection $connection;

    /**
     * @param OrderRepository $orderRepository
     * @param OrderItemRepository $orderItemRepository
     */
    public function __construct(OrderRepository $orderRepository, OrderItemRepository $orderItemRepository, Connection $connection)
    {
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->connection = $connection;
    }

    /**
     * Vytvoří objednávku z košíku (snapshot do order_item), spočítá součty, uloží do DB a vyprázdní košík.
     *
     * @param int  $userId ID přihlášeného uživatele
     * @param Cart $cart   Košík uživatele (musí obsahovat položky)
     * @param array $data  Data z checkout formuláře
     *
     * @return Order Nově vytvořená objednávka
     * @throws \Throwable
     */
    public function createOrderFromCart(int $userId, Cart $cart, array $data): Order {
        if (empty($cart->items)) {
            throw new \RuntimeException('Košík je prázdný.');
        }
        $shippingMethod = (string) ($data['shippingMethod'] ?? '');
        $paymentMethod  = (string) ($data['paymentMethod'] ?? '');

        if ($shippingMethod === '' || $paymentMethod === '') {
            throw new \RuntimeException('Vyberte dopravu a platbu.');
        }

        if (!isset(Order::SHIPPING_PRICES[$shippingMethod])) {
            throw new \RuntimeException('Neplatná metoda dopravy.');
        }
        if (!isset(Order::PAYMENT_LABELS[$paymentMethod])) {
            throw new \RuntimeException('Neplatná metoda platby.');
        }

        // totals z položek košíku
        $itemsTotal = 0.0;
        $snapshots = [];

        foreach ($cart->items as $item) {
            $unitPrice = (float) $item->product->price;
            $count = (int) $item->count;

            $lineTotal = $unitPrice * $count;
            $itemsTotal += $lineTotal;

            $snapshots[] = [
                'product'      => $item->product,
                'productTitle' => (string) $item->product->title,
                'unitPrice'    => $unitPrice,
                'count'        => $count,
                'color'        => $item->color ?? null,
                'size'         => $item->size ?? null,
                'lineTotal'    => $lineTotal,
            ];
        }

        $shippingPrice = Order::shippingPrice($shippingMethod);
        $grandTotal = $itemsTotal + $shippingPrice;

        $this->connection->query('START TRANSACTION');
        try {
            // 1) New Order
            $order = new Order();
            $order->userId = $userId;
            $order->cartId = (int) $cart->cartId;
            $order->status = 'new';
            $order->customerName    = (string) ($data['customerName'] ?? '');
            $order->customerEmail   = (string) ($data['customerEmail'] ?? '');
            $order->phone           = $data['phone'] ?? '';
            $order->deliveryAddress = (string) ($data['deliveryAddress'] ?? '');
            $order->note            = $data['note'] ?? null;
            $order->shippingMethod = $shippingMethod;
            $order->shippingPrice  = $shippingPrice;
            $order->paymentMethod  = $paymentMethod;
            $order->itemsTotal = $itemsTotal;
            $order->grandTotal = $grandTotal;

            // persist => vyplní orderId
            $this->orderRepository->persist($order);

            // 2) OrderItem snapshoty
            foreach ($snapshots as $snapshot) {
                $orderItem = new OrderItem();

                // vztahy (LeanMapper)
                $orderItem->order = $order; // m:hasOne => order_id
                $orderItem->product = $snapshot['product']; // m:hasOne => product_id

                // snapshot data
                $orderItem->productTitle = $snapshot['productTitle'];
                $orderItem->unitPrice = $snapshot['unitPrice'];
                $orderItem->count = $snapshot['count'];
                $orderItem->color = $snapshot['color'];
                $orderItem->size = $snapshot['size'];
                $orderItem->lineTotal = $snapshot['lineTotal'];

                $this->orderItemRepository->persist($orderItem);
            }

            // 3) Vyprázdnit košík
            $this->connection->query('DELETE FROM cart_item WHERE cart_id = %i', $cart->cartId);

            // aktualizace timestampu košíku
            $this->connection->query('UPDATE cart SET last_modified = NOW() WHERE cart_id = %i', $cart->cartId);

            $this->connection->query('COMMIT');

            return $order;
        } catch (\Throwable $e) {
            $this->connection->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Metoda pro získání jedné objednávky
     * @param int $id
     * @return Order
     * @throws \Exception
     */
    public function getOrder(int $id): Order {
        return $this->orderRepository->find($id);
    }

    /**
     * Metoda pro vyhledání objednávek
     * @param array|null $params = null
     * @param int $offset = null
     * @param int $limit = null
     * @return Order[]
     */
    public function findOrders(array $params = null, int $offset = null, int $limit = null): array {
        return $this->orderRepository->findAllBy($params, $offset, $limit);
    }

    /**
     * Metoda pro vrácení položek konkrétní objednávky (snapshoty uložené v order_item).
     * @param int $orderId ID objednávky.
     * @return OrderItem[]
     * @throws \Dibi\Exception
     * @throws \LeanMapper\Exception\InvalidStateException
     */
    public function getOrderItems(int $orderId): array {
        return $this->orderItemRepository->findAllBy(['order_id' => $orderId]);
    }

    /**
     * Metoda pro změnu stavu objednávky.
     * @param int $orderId
     * @param string $newStatus
     * @return void
     * @throws \Exception
     */
    public function changeStatus(int $orderId, string $newStatus): void {
        $order = $this->orderRepository->find($orderId);
        $order->status = $newStatus;
        $this->orderRepository->persist($order);
    }


}