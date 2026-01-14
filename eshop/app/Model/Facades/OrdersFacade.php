<?php

namespace App\Model\Facades;

use App\Model\Entities\Order;
use App\Model\Entities\OrderItem;
use App\Model\Repositories\OrderItemRepository;
use App\Model\Repositories\OrderRepository;
use App\Model\Entities\User;
use Dibi\Exception;
use LeanMapper\Connection;
use LeanMapper\Exception\InvalidStateException;

/**
 * Class OrdersFacade
 * @package App\Model\Facades
 */
class OrdersFacade{
    private OrderRepository $orderRepository;
    private OrderItemRepository $orderItemRepository;

    /**
     * @param OrderRepository $orderRepository
     * @param OrderItemRepository $orderItemRepository
     */
    public function __construct(OrderRepository $orderRepository, OrderItemRepository $orderItemRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
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

    /**
     * Metoda pro získání objednávek jediného uživatele.
     * @param int $userId
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws Exception
     * @throws InvalidStateException
     */
    public function getUserOrders(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        $filters = ['user_id' => $userId];

        // volá se metoda findAllBy v repository
        return $this->orderRepository->findAllBy($filters,'created_at DESC', $offset, $limit);
    }


}