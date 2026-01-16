<?php


namespace App\Model\Entities;


use LeanMapper\Entity;

/**
 * Class CartItem
 * @package App\Model\Entities
 * @property int $cartItemId
 * @property Product $product m:hasOne
 * @property Cart $cart m:hasOne
 * @property string|null $color
 * @property string|null $size
 * @property int $count = 0
 */
class CartItem extends Entity{

}