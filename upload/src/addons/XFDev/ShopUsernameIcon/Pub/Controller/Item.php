<?php

namespace XFDev\ShopUsernameIcon\Pub\Controller;

use XF\Mvc\ParameterBag;

class Item extends XFCP_Item{
    /**
     * XFDev - Displays Lists of Users having this item
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionMembers(ParameterBag $params): \XF\Mvc\Reply\View
    {
        $item = $this->assertViewableItem($params->item_id);

        $membersFinder = $this->finder('DBTech\Shop:Purchase')->where('item_id','=',$params->item_id);

        //Pagination Data
        $totalMembers = $membersFinder->total();
        $page = $this->filterPage();
        $perPage = 10;

        $membersFinder->limitByPage($page,$perPage);
        $members = $membersFinder->fetch();

        $this->assertCanonicalUrl($this->buildLink('dbtech-shop/members',$item));

        $viewParams = [
            'members'   =>  $members,
            'item'  =>   $item,

            'total' => $totalMembers,
            'page' => $page,
            'perPage' => $perPage
        ];

        return $this->view('XFDev\ShopCustomItem:Item\Members','xfdev_dbtech_shop_item_members',$viewParams);
    }
}