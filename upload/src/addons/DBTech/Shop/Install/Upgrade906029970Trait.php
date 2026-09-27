<?php

namespace DBTech\Shop\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Schema\Alter;
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
trait Upgrade906029970Trait
{
	/**
	 *
	 */
	public function upgrade906020033Step1(): void
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		$currencies = $db->fetchAll('
			SELECT `column`
			FROM xf_dbtech_shop_currency
		');

		$sm->alterTable('xf_user', function (Alter $table) use ($currencies)
		{
			foreach ($currencies AS $currency)
			{
				if ($table->getColumnDefinition($currency['column']))
				{
					$table->changeColumn($currency['column'], 'decimal')
						->length('65,8')
						->unsigned(false)
						->setDefault(0)
					;
				}
			}
		});
	}

	/**
	 *
	 */
	public function upgrade906020033Step2(): void
	{
		$this->applyTables();
	}
}