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

interface PurchasableEntityInterface extends MinimalPurchasableEntityInterface
{
    public function canPurchase(&$error = null);

    public function getCostPhrase();

    public function getPricePaid(\XF\Entity\PurchaseRequest $purchaseRequest);
}
