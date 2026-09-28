<?php

namespace DBTech\Shop\ItemType;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\Purchase;

/**
 * Class ItemBundle
 *
 * @package DBTech\Shop\ItemType
 */
class ItemBundle extends AbstractHandler
{
    /** @var array */
    protected $defaultAdminConfig = [
        'bundle_items' => []
    ];

    /**
     * @param array $config
     *
     * @return array
     */
    public function filterAdminConfig(array $config = []): array
    {
        $bundleItems = [];
        
        if (!empty($config['bundle_items']) && is_array($config['bundle_items']))
        {
            foreach ($config['bundle_items'] as $itemData)
            {
                if (empty($itemData['item_id']) || empty($itemData['quantity']))
                {
                    continue;
                }
                
                $bundleItems[] = $this->app()->inputFilterer()->filterArray($itemData, [
                    'item_id' => 'uint',
                    'quantity' => 'uint'
                ]);
            }
        }
        
        return [
            'bundle_items' => $bundleItems
        ];
    }

    /**
     * @return string|null
     */
    public function getAdminConfigTemplate(): ?string
    {
        return 'admin:dbtech_shop_admin_config_itembundle';
    }

    /**
     * @param string $context
     *
     * @return array
     */
    protected function getDefaultTemplateParams(string $context): array
    {
        $params = parent::getDefaultTemplateParams($context);
        
        switch ($context)
        {
            case 'admin_config':
                /** @var \DBTech\Shop\Repository\Item $itemRepo */
                $itemRepo = $this->getItemRepo();
                $params['availableItems'] = $itemRepo->getItemTitlePairs();
                break;
        }
        
        return $params;
    }

    /**
     * @return bool
     */
    public function canRevertConfiguration(): bool
    {
        return false; // Bundle items are granted permanently
    }

    /**
     * Add any required listeners (even if empty for bundles)
     */
    public function addListeners()
    {
        // ItemBundle doesn't need listeners, but method must exist
    }

    /**
     * @throws \XF\PrintableException
     */
    protected function activateAlways()
    {
        $this->grantBundleItems();
    }

    /**
     * Grant all items in the bundle to the user
     *
     * @throws \XF\PrintableException
     */
    protected function grantBundleItems()
    {
        if (empty($this->item->code['bundle_items']))
        {
            return;
        }

        $purchase = $this->purchase;
        $user = $purchase->User;
        
        if (!$user)
        {
            return;
        }

        foreach ($this->item->code['bundle_items'] as $bundleItem)
        {
            $itemId = $bundleItem['item_id'];
            $quantity = $bundleItem['quantity'];

            /** @var Item $item */
            $item = $this->em()->find('DBTech\Shop:Item', $itemId);
            
            if (!$item || !$item->canPurchaseForUser($user, false))
            {
                continue;
            }

            // Create purchases for each quantity
            for ($i = 0; $i < $quantity; $i++)
            {
                /** @var Purchase $itemPurchase */
                $itemPurchase = $this->em()->create('DBTech\Shop:Purchase');
                $itemPurchase->bulkSet([
                    'item_id' => $item->item_id,
                    'user_id' => $user->user_id,
                    'buyer_user_id' => $purchase->buyer_user_id,
                    'buyer_username' => $purchase->buyer_username,
                    'dateline' => \XF::$time,
                    'expiry_date' => 0,
                    'gifted' => $purchase->gifted,
                    'message' => $purchase->message,
                    'active' => true,
                    'hidden' => false,
                    'configured' => false,
                    'traded' => false
                ]);

                // Add bundle_purchase_id if the column exists
                if ($this->hasBundlePurchaseColumn())
                {
                    $itemPurchase->set('bundle_purchase_id', $purchase->purchase_id);
                }

                $itemPurchase->save();

                // Activate the item
                $handler = $item->getHandler();
                if ($handler)
                {
                    $handler->setContent($item, $itemPurchase)
                        ->setPerformValidations(false)
                        ->logIp(false)
                        ->afterPurchase();
                }
            }
        }
    }

    /**
     * Check if bundle_purchase_id column exists
     *
     * @return bool
     */
    protected function hasBundlePurchaseColumn(): bool
    {
        try
        {
            $sm = \XF::db()->getSchemaManager();
            return $sm->columnExists('xf_dbtech_shop_purchase', 'bundle_purchase_id');
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    public function deactivate(&$error = null): bool
    {
        // When deactivating bundle, also deactivate all bundle items
        $this->discardBundleItems();
        return true;
    }

    /**
     * @param null $error
     *
     * @return bool
     */
    protected function _discard(&$error = null): bool
    {
        // When discarding bundle, also discard all bundle items
        $this->discardBundleItems();
        return true;
    }

    /**
     * Discard all items that were granted as part of this bundle
     */
    protected function discardBundleItems()
    {
        if (!$this->purchase || !$this->hasBundlePurchaseColumn())
        {
            return;
        }

        $bundlePurchases = $this->finder('DBTech\Shop:Purchase')
            ->where('bundle_purchase_id', $this->purchase->purchase_id)
            ->fetch();

        foreach ($bundlePurchases as $bundlePurchase)
        {
            /** @var Purchase $bundlePurchase */
            $handler = $bundlePurchase->Item->getHandler();
            if ($handler)
            {
                $handler->setContent($bundlePurchase->Item, $bundlePurchase)
                    ->setPerformValidations(false)
                    ->logIp(false)
                    ->discard($null, 'bundle_discard');
            }
        }
    }
}