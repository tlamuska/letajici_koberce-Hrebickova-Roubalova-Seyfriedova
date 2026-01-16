<?php

namespace App\Model\Repositories;

use App\Model\Entities\Product;
/**
 * Class ProductRepository
 * @package App\Model\Repositories
 */
class ProductRepository extends BaseRepository{

    /**
     * @param array|null $whereArr
     * @param int|null $offset
     * @param int|null $limit
     * @return Product[]
     * @throws \Dibi\Exception
     * @throws \LeanMapper\Exception\InvalidStateException
     */
    public function findAllBy($whereArr = null, $offset = null, $limit = null)
    {
        $query = $this->connection->select('*')->from($this->getTable());

        // 1. Zpracování vyhledávání (Search)
        if (!empty($whereArr['search'])) {
            // Použijeme Dibi syntaxi s otazníkem pro bezpečné vložení řetězce
            $query->where('title LIKE ?', '%' . $whereArr['search'] . '%');
            unset($whereArr['search']); // Odstraníme, aby to BaseRepository nebralo jako sloupec
        }

        // 2. Zpracování řazení (Order)
        if (!empty($whereArr['order'])) {
            $query->orderBy($whereArr['order']);
            unset($whereArr['order']);
        }

        // 3. Zbytek parametrů (standardní filtrování)
        if (!empty($whereArr)) {
            $query->where($whereArr);
        }

        // createEntities vrací obecné Entity[], ale my víme, že to jsou Product[], proto to IDE může hlásit
        // ale funkčně je to v pořádku.
        return $this->createEntities($query->fetchAll($offset, $limit));
    }

    /**
     * @param array|null $whereArr
     * @return int
     * @throws \Dibi\Exception
     */
    public function findCountBy($whereArr = null)
    {
        $query = $this->connection->select('count(*)')->from($this->getTable());

        if (!empty($whereArr['search'])) {
            $query->where('title LIKE ?', '%' . $whereArr['search'] . '%');
            unset($whereArr['search']);
        }

        if (!empty($whereArr['order'])) {
            unset($whereArr['order']);
        }

        if (!empty($whereArr)) {
            $query->where($whereArr);
        }

        return $query->fetchSingle();
    }

    public function search(string $query, array $filters = [], int $offset = null, int $limit = null): array
    {
        $fluent = $this->connection->command();
        $fluent->select('p.*')
            ->from('product p')
            ->where('p.title LIKE ? OR p.description LIKE ?', '%' . $query . '%', '%' . $query . '%');
        if (!empty($filters['order'])) {
            $fluent->orderBy('p.' . $filters['order']);
            unset($filters['order']);
        }
        foreach ($filters as $key => $value) {
            $fluent->where('p.' . $key . ' = ?', $value);
        }

        if ($limit !== null) $fluent->limit($limit);
        if ($offset !== null) $fluent->offset($offset);

        $rows = $fluent->fetchAll();
        return $this->createEntities($rows);
    }
}