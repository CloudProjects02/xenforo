<?php

namespace DBTech\Credits\Install;

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
trait Upgrade905069970Trait
{
	/**
	 *
	 */
	public function upgrade905060032Step1(): void
	{
		$this->applyTables();
	}

	/**
	 *
	 */
	public function upgrade905060033Step1(): void
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		$currencies = $db->fetchAll('
			SELECT `column`
			FROM xf_dbtech_credits_currency
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
	public function upgrade905060033Step2(): void
	{
		$this->applyTables();
	}

	/**
	 *
	 */
	public function upgrade905060034Step1(): void
	{
		$this->db()->delete('xf_user_alert', 'content_type = ? AND action = ?', [
			'dbtech_credits_txn',
			'payment',
		]);
	}
}