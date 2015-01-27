<?php

namespace yz\shoppingcart;


/**
 * Interface CartItemInterface
 * @property int    $price
 * @property int    $cost
 * @property string $id
 * @property string $wishlist
 * @property int    $quantity
 * @package yz\shoppingcart
 */
interface CartPositionInterface
{
    /** Triggered on cost calculation */
    const EVENT_COST_CALCULATION = 'costCalculation';

    // test

    /**
     * @return integer
     */
    public function getPrice();

    /**
     * @param bool $withDiscount
     *
     * @return integer
     */
    public function getCost($withDiscount = true);

    /**
     * @return string
     */
    public function getId();

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity);

    /**
     * @return int
     */
    public function getQuantity();

    /**
     * @return mixed
     */
    public function getWishlist();

    /**
     * @param string $wishlist
     *
     * @return mixed
     */
    public function setWishlist($wishlist);

    /**
     * @return mixed
     */
    public function getOldPrice();

    /**
     * @param string $price
     *
     * @return mixed
     */
    public function setOldPrice($price);

} 