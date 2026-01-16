<?php

namespace App\Model\Repositories;

use App\Model\Entities\Order;

/**
 * Class OrderRepository
 * @package App\Model\Repositories
 */
class OrderRepository extends BaseRepository
{

    /**
     * @param array|null $whereArr
     * @param int|null $offset
     * @param int|null $limit
     * @return Order[]
     * @throws \Dibi\Exception
     * @throws \LeanMapper\Exception\InvalidStateException
     */

    public function findAllBy($whereArr = null, $order = null, $offset = null, $limit = null): array
    {
        $query = $this->connection->select('*')->from($this->getTable());

        if (!empty($whereArr)) {
            $query->where($whereArr);
        }

        if ($order) {
            $query->order($order);
        }

        return $this->createEntities($query->fetchAll($offset, $limit));
    }

}