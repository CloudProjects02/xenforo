<?php

namespace DBTech\Credits\Install;

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
trait Upgrade905089970Trait
{
	/**
	 *
	 */
	public function upgrade905080070Step1(): void
	{
		$this->applyTables();
	}
}