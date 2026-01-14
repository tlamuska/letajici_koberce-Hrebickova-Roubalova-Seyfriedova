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

    public function findAllBy($whereArr = null, $offset = null, $limit = null) {
        $query = $this->connection->select('*')->from($this->getTable());

        return $this->createEntities($query->fetchAll($offset, $limit));
    }

}