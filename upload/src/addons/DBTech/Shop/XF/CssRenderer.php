<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF;

/**
 * @extends \XF\CssRenderer
 */
class CssRenderer extends XFCP_CssRenderer
{
	protected function getRenderParams()
	{
		$params = parent::getRenderParams();

		if ($this->includeExtraParams)
		{
			$params['dbtechShopUserNameStyles'] = \XF::app()->container('dbtechShop.usernameStyles');
			$params['dbtechShopUserTitleStyles'] = \XF::app()->container('dbtechShop.usertitleStyles');
			$params['dbtechShopAvatarStyles'] = \XF::app()->container('dbtechShop.avatarStyles');
		}

		return $params;
	}
}