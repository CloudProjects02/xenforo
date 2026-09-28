<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Searcher;

class User extends XFCP_User
{
	public function getFormDefaults()
	{
		$previous = parent::getFormDefaults();
		
		$previous['dbtech_shop_item_count'] = ['end' => -1];
		$previous['dbtech_shop_purchases'] = ['end' => -1];
		
		return $previous;
	}
}