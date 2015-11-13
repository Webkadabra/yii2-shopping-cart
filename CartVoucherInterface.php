<?php

namespace yz\shoppingcart;


/**
 * Interface CartItemInterface
 * @property float    $price
 * @property integer  $percent
 * @property string $id
 * @package yz\shoppingcart
 */
interface CartVoucherInterface
{
    /**
     * @return float
     */
    public function getPrice();

    /**
     * @return integer
     */
    public function getPercent();

    /**
     * @return string
     */
    public function getId();

} 