<?php

namespace XFDev\ShopUsernameIcon\Entity;

/**
 * COLUMNS
 * @property int purchase_id
 * @property int user_id
 * @property int buyer_user_id
 * @property string buyer_username
 * @property int item_id
 * @property int dateline
 * @property array configuration
 * @property string message
 * @property bool active
 * @property bool hidden
 * @property bool configured
 * @property bool gifted
 * @property bool traded
 * @property int expiry_date
 * @property int discussion_thread_id
 *
 * GETTERS
 * @property \DBTech\Shop\ItemType\AbstractHandler|null handler
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Item Item
 * @property \XF\Entity\User User
 * @property \XF\Entity\User Buyer
 * @property \XF\Entity\Thread Discussion
 */
class Purchase extends XFCP_Purchase
{
    public function isShopUsernameIcon(): bool
    {
        return $this->Item->item_type_id == 'shopusernameicon';
    }

    protected function _preSave()
    {
        parent::_preSave();

        if($this->isInsert())
        {
            if($this->isShopUsernameIcon())
            {
                $this->active = false;
            }
        }
    }
}