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
trait Upgrade906049970Trait
{
	/**
	 *
	 */
	public function upgrade906040870Step1()
	{
		$this->applyTables();
	}
}