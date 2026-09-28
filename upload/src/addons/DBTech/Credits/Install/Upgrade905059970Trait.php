<?php

namespace DBTech\Credits\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Exception;
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
trait Upgrade905059970Trait
{
	/**
	 *
	 * @throws Exception
	 */
	public function upgrade905050031Step1(): void
	{
		$this->query("
			REPLACE INTO `xf_payment_provider`
				(`provider_id`, `provider_class`, `addon_id`)
			VALUES
				('dbtech_credits', 'DBTech\\\\Credits:Credits', X'4442546563682F43726564697473')
		");
	}
}