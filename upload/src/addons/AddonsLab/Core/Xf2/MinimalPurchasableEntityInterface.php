<?php

namespace AddonsLab\Core\Xf2;

/**
 * Interface PurchasableEntityInterface
 * @package AddonsLab\Core\Xf2
 * Any entity that should be purchasable can implement this interface for purchase-related functionality
 * The following getters should be added to the entity
 [
 'cost_phrase' => true,
 'purchasable_type_id' => true
 ]
 *
 */

interface MinimalPurchasableEntityInterface
{
    public function getPurchasableTitle();

    public function getPrice();

    public function getCurrency();

    public function getPaymentProfileIds();

    public function getPurchasableTypeId();

    public function getPurchasableItemId();
}
