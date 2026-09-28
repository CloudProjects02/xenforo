<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF;

class CssRenderer extends XFCP_CssRenderer
{
	protected function getRenderParams()
	{
		$params = parent::getRenderParams();
		
		if ($this->includeExtraParams)
		{
			$params['dbtechShopUserNameStyles'] = $this->app->container('dbtechShop.usernameStyles');
			$params['dbtechShopUserTitleStyles'] = $this->app->container('dbtechShop.usertitleStyles');
		}
		
		return $params;
	}
}