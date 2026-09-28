<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Template;

use XF\Mvc\Entity\ArrayCollection;

class Templater extends XFCP_Templater
{
	/**
	 * @param $templater
	 * @param $escape
	 * @param $user
	 * @param bool $withBanner
	 * @param array $attributes
	 *
	 * @return string
	 */
	public function fnUserTitle($templater, &$escape, $user, $withBanner = false, $attributes = [])
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */
		
		$userIsValid = ($user instanceof \XF\Entity\User);
		if (!$userIsValid)
		{
			return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
		}
		
		/** @var \DBTech\Shop\XF\Entity\User $user */
		
		if (!$user->user_id)
		{
			return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
		}
		
		if (!empty($attributes['preview']))
		{
			return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
		}
		
		$classes = [];
		
		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = \XF::repository('DBTech\Shop:Purchase')->filterActivePurchasesForUser($user);
		
		foreach ($purchases as $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('user_title_style_classes', [&$classes]);
		}

		if ($classes)
		{
			// Make sure this is set
			$attributes['class'] = isset($attributes['class']) ? $attributes['class'] : '';

			// Ensure we only add the span if needed
			$attributes['class'] .= ' ' . implode(' ', $classes);
		}

		return parent::fnUserTitle($templater, $escape, $user, $withBanner, $attributes);
	}

    public function fnUsernameLink($templater, &$escape, $user, $rich = false, $attributes = [])
    {
        $parent = parent::fnUsernameLink($templater, $escape, $user, $rich, $attributes);

        if (is_array($user) || empty($user) || !($user instanceof \XF\Entity\User)) {
            return $parent;
        }

        $userItemIcon = '';
        $viewableItems = \XF::repository('DBTech\Shop:Purchase')->findPurchasesForUser($user)
            ->with(['Item'])
            ->where('Item.item_type_id', 'usernameitem')
            ->where('active', 1)
            ->fetch();

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
                $userItem = $this->templaterUsernameItemIcon($templater, $null, $purchase->Item, $itemIconSize);
                $link = \XF::app()->router()->buildLink('dbtech-shop/members', $purchase->Item);
                $userItemIcon .= "<a href='{$link}' class='xfdev-item-link' style='padding: 0; display: inline-block'>"
                    . "<span class='xfdev_shop_item-icon' title='{$titleExtra}'>{$userItem}</span></a>";
            }
        }

        // Return combined username and icons
        return $parent . ' ' . $userItemIcon;
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
    public static function templaterUsernameItemIcon(
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
	
	/**
	 * @param $templater
	 * @param $escape
	 * @param $user
	 * @param bool $includeGroupStyling
	 *
	 * @return string
	 */
	public function fnUsernameClasses($templater, &$escape, $user, $includeGroupStyling = true)
	{
		$parentClasses = parent::fnUsernameClasses($templater, $escape, $user, $includeGroupStyling);
		
		if (empty($user['user_id']))
		{
			return $parentClasses;
		}
		
		$userIsValid = ($user instanceof \XF\Entity\User);
		if (!$userIsValid)
		{
			if (!\XF::options()->dbtech_shop_forceformatting)
			{
				return $parentClasses;
			}
			
			/** @var \DBTech\Shop\XF\Entity\User $user */
			$user = \XF::em()->find('XF:User', $user['user_id']);
		}
		
		if (!$user)
		{
			return $parentClasses;
		}
		
		
		$classes = [];
		
		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = \XF::repository('DBTech\Shop:Purchase')->filterActivePurchasesForUser($user);
		
		foreach ($purchases as $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('username_style_classes', [&$classes]);
		}
		
		$escape = false; // note: not doing this explicitly, shouldn't be needed for the output format
		
		if (empty($classes))
		{
			return $parentClasses;
		}
		
		return $parentClasses . ' ' . implode(' ', $classes);
	}
}