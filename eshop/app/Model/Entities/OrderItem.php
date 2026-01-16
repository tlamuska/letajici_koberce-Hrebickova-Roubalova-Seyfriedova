<?php

namespace App\Model\Entities;

use LeanMapper\Entity;

/**
 * Class OrderItem
 * @package App\Model\Entities
 *
 * @property int $orderItemId
 * @property Order $order m:hasOne
 * @property Product $product m:hasOne
 * @property string $productTitle
 * @property float $unitPrice
 * @property int $count
 * @property string|null $color
 * @property string|null $size
 * @property float $lineTotal
 */

class OrderItem extends Entity
{

}