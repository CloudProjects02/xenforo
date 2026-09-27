<?php

namespace DBTech\Shop\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Schema\Column;
use XF\Db\SchemaManager;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade906009970Trait
{
	/**
	 *
	 */
	public function upgrade906000031Step1(): void
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