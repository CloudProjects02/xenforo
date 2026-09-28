<?php

namespace XFDev\ShopUsernameIcon\Repository;

class Purchase extends XFCP_Purchase
{
    /**
     * @param \XF\Entity\User $user
     * @param bool $checkVisibility
     *
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getUsernameIconPurchasesForUserPostbit(\XF\Entity\User $user, $checkVisibility = true): \XF\Mvc\Entity\ArrayCollection
    {
        return $this->filterActivePurchasesForUser($user, $checkVisibility)
            ->filter(function(\DBTech\Shop\Entity\Purchase $purchase)
            {
                /*
                 * XFDev
                 */
                if (!$purchase->isShopUsernameIcon())
                {
                    return null;
                }

                return $purchase;
            });
    }
}