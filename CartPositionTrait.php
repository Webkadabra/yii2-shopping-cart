<?php

namespace yz\shoppingcart;

use yii\base\Component;
use yii\base\Object;

/**
 * Trait CartPositionTrait
 * @property int    $quantity Returns quantity of cart position
 * @property string $wishlist Returns wishlist value
 * @property int    $cost     Returns cost of cart position. Default value is 'price * quantity'
 * @property int    $oldPrice Item price from previous order
 * @package yz\shoppingcart
 */
trait CartPositionTrait
{
    protected $_voucherId = null;
    protected $_quantity;
    protected $_oldPrice = null;

    /**
     * @return int
     */
    public function getQuantity() {
        return $this->_quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity) {
        $this->_quantity = $quantity;
    }

    /**
     * @return int
     */
    public function getOldPrice() {
        return $this->_oldPrice;
    }

    /**
     * @param int $price
     */
    public function setOldPrice($price) {
        $this->_oldPrice = $price;
    }

    /**
     * @return int
     */
    public function getVoucherId() {
        return $this->_voucherId;
    }

    /**
     * @param int $id
     */
    public function setVoucherId($id) {
        $this->_voucherId = $id;
    }

    /**
     * Default implementation for getCost function. Cost is calculated as price * quantity
     * @param bool $withDiscount
     * @return int
     */
    public function getCost($withDiscount = true)
    {
        /** @var Component|CartPositionInterface|self $this */
        $cost = $this->getQuantity() * $this->getPrice();
        $costEvent = new CostCalculationEvent([
            'baseCost' => $cost,
        ]);
        if ($this instanceof Component)
            $this->trigger(CartPositionInterface::EVENT_COST_CALCULATION, $costEvent);
        if ($withDiscount)
            $cost = max(0, $cost - $costEvent->discountValue);
        return $cost;
    }
} 
