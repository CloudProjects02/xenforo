<?php

namespace DBTech\Shop\Install;

/**
 * @property \XF\AddOn\AddOn addOn
 * @property \XF\App app
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 * @method \XF\Db\Schema\Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade906009970Trait
{
	/**
	 *
	 */
	public function upgrade906000031Step1()
	{
		$this->insertNamedWidget('dbtech_shop_profilemusic');
		$this->insertNamedWidget('dbtech_shop_wallet');
		$this->insertNamedWidget('dbtech_shop_cart');

		// Purge the cache
		\XF::registry()->delete([
			'dbt_shop_category',
			'dbt_shop_currency',
			'dbt_shop_item',
			'dbt_shop_itemtype',
			'dbt_shop_lottery',
			'dbt_shop_lotteryprize',
			'dbt_shop_shop',

			'dbtech_shop_category',
			'dbtech_shop_currency',
			'dbtech_shop_item',
			'dbtech_shop_itemtype',
			'dbtech_shop_lottery',
			'dbtech_shop_lotteryprize',
			'dbtech_shop_shop',
		]);
	}
}