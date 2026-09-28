<?php

namespace XFDev\ShopUsernameIcon\XF\Template;

class Templater extends XFCP_Templater
{

    public function fnUsernameLink($templater, &$escape, $user, $rich = false, $attributes = [])
    {

        $parent = parent::fnUsernameLink($templater, $escape, $user, $rich, $attributes);

        if (is_array($user)) {
            return $parent;
        }

//        if($templater->currentTemplateName == 'message_macros' || $templater->currentTemplateName == 'member_view'){
        $userItemIcon = '';
        if (!empty($user) && $user instanceof \XF\Entity\User) {
            $viewableItems = \XF::repository('DBTech\Shop:Purchase')->getUsernameIconPurchasesForUserPostbit($user);
        }

        if (!empty($viewableItems)) {
            foreach ($viewableItems as $purchase) {
                if (\XF::options()->dbtech_shop_enablegiftgiver && $purchase->gifted) {
                    if ($purchase->message) {
                        $titleExtra = \XF::phrase('dbtech_shop_gift_received_x_from_y_message', [
                            'user' => $purchase->buyer_username,
                            'message' => $purchase->message,
                        ]);
                    } else {
                        $titleExtra = \XF::phrase('dbtech_shop_gift_received_x_from_y', [
                            'user' => $purchase->buyer_username,
                        ]);
                    }
                } else {
                    $titleExtra = $purchase->Item->title;
                }

                $itemIconSize = $attributes['itemIconSize'] ?? '20px';
                $userItem = $this->templaterFnEgxItemIcon($templater, $null, $purchase->Item, $itemIconSize);
                $link = \XF::app()->router()->buildLink('dbtech-shop/members', $purchase->Item);
                $userItemIcon = "<a href='{$link}' class='xfdev-item-link' style='padding: 0; display: inline-block'><span class='xfdev_shop_item-icon' title='{$titleExtra}'>$userItem</span></a>";
            }
        }

        return "$parent $userItemIcon";
//        }

//        return $parent;
    }


    /**
     * @param $templater
     * @param $escape
     * @param \DBTech\Shop\Entity\Item $item
     * @param string $size
     * @param string $href
     * @param string $xfClick
     *
     * @return string
     */
    public static function templaterFnEgxItemIcon(
        $templater, &$escape, \DBTech\Shop\Entity\Item $item, string $size, string $href = '', string $xfClick = ''
    ): string
    {
        $escape = false;

        if ($href) {
            $tag = 'a';
            $hrefAttr = 'href="' . htmlspecialchars($href) . '" data-xf-click="' . $xfClick . '"';
        } else {
            $tag = 'span';
            $hrefAttr = '';
        }

        if (!$item->icon_date) {
            return "<{$tag} {$hrefAttr}><span></span></{$tag}>";
        }

        $src = $item->getIconUrl($size);

        return "<{$tag} {$hrefAttr}>"
            . '<img style="height:' . $size . '; width: ' . $size . '" src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($item->title) . '" loading="lazy" />'
            . "</{$tag}>";
    }
}