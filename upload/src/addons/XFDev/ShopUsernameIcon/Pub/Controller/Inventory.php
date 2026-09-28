<?php

namespace XFDev\ShopUsernameIcon\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\ArrayCollection;
use XFDev\ShopCustomItem\Repository\Purchase;

class Inventory extends XFCP_Inventory
{

    /**
     * @param ParameterBag $params
     *
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionIndex(ParameterBag $params)
    {
        $parent = parent::actionIndex($params);

        if ($params->purchase_id){
            return $parent;
        }

        $inventory = $parent->getParam("inventory");
        $currentPurchases = $inventory->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
        {

            if(\XF::isAddOnActive('XFDev/ShopUserBanners')){
                if($purchase->isShopUsernameIcon() || $purchase->isShopUserBanners())
                {
                    return  null;
                }
            }else{
                if($purchase->isShopUsernameIcon())
                {
                    return  null;
                }
            }

            if (!$purchase->isExpired())
            {
                return $purchase;
            }

            return null;
        });

        $expiredPurchases = $inventory->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
        {
            if(\XF::isAddOnActive('XFDev/ShopUserBanners')){
                if($purchase->isShopUsernameIcon() || $purchase->isShopUserBanners())
                {
                    return  null;
                }
            }else{
                if($purchase->isShopUsernameIcon())
                {
                    return  null;
                }
            }


            if ($purchase->isExpired())
            {
                return $purchase;
            }

            return null;
        });

        $shopUsernameIcon = $inventory->filter(function(\DBTech\Shop\Entity\Purchase $purchase)
        {
            if($purchase->isShopUsernameIcon())
            {
                return $purchase;
            }

            return null;
        });

        $inventoryGrouped = $currentPurchases->groupBy('active');

        $parent->setParam('inventory', $inventory);
        $parent->setParam('expiredPurchases', $expiredPurchases);
        $parent->setParam('shopUsernameIcon', $shopUsernameIcon);
        $parent->setParam('activePurchases', $inventoryGrouped[1] ?? []);
        $parent->setParam('inactivePurchases', $inventoryGrouped[0] ?? []);

        return $parent;
    }

    /**
     * @param ParameterBag $params
     *
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionSettings(ParameterBag $params)
    {
        $purchase = $this->assertViewablePurchase($params->purchase_id);

        if ($this->isPost())
        {
            $isActive = $this->filter('active', 'bool');

            if (!$purchase->isActive() && $isActive)
            {
                if ($purchase->Item->item_type_id == 'shopusernameicon')
                {
                    $this->disableOtherShopUsernameIcons($purchase);
                }
            }
        }

       return parent::actionSettings($params);
    }

    private function disableOtherShopUsernameIcons(\DBTech\Shop\Entity\Purchase $purchase)
    {
        /** @var \DBTech\Shop\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        /**
         * @var Purchase $repo
         */
        $purchases = $this->repository('DBTech\Shop:Purchase')->findPurchasesForUser($visitor->user_id)
            ->where('Item.item_type_id','shopusernameicon')
            ->where('purchase_id','<>',$purchase->purchase_id)
            ->fetch();

        foreach ($purchases as $purchase)
        {
            $handler = $purchase->handler;
            $error = '';
            $handler->deactivate($error);
        }

    }
}