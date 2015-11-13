<?php

namespace yz\shoppingcart;



interface CartOfferInterface
{
    /**
     * @param CartPositionInterface[] $positions
     * @return CartPositionInterface[] array
     */
    public static function calculateBestOffer($positions);

} 