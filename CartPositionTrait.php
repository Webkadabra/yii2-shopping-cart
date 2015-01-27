<?php

namespace yz\shoppingcart;

use yii\base\Model;

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
    protected $_quantity;
    protected $_oldPrice;
    protected $_wishlist;

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
     * @return string
     */
    public function getWishlist() {
        return $this->_wishlist;
    }

    /**
     * @param string $wishlist
     */
    public function setWishlist($wishlist) {
        $this->_wishlist = $wishlist;
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
     * Default implementation for getCost function. Cost is calculated as price * quantity
     *
     * @param bool $withDiscount
     *
     * @return int
     */
    public function getCost($withDiscount = true) {
        /** @var Model|CartPositionInterface|self $this */
        $cost = $this->getQuantity() * $this->getPrice();
        $costEvent = new CostCalculationEvent([
            'baseCost' => $cost,
        ]);
        $this->trigger(CartPositionInterface::EVENT_COST_CALCULATION, $costEvent);
        if ($withDiscount)
            $cost -= $costEvent->discountValue;
        return $cost;
    }
} 
