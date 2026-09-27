<?php

namespace DBTech\Shop\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\SchemaManager;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 */
trait UninstallDataTrait
{
	/**
	 * Methods MUST start at step 4, as steps 1-3 are reserved by the core
	 */

	protected function runMiscCleanUp(): void
	{
		// Get rid of change logs
		$this->db()->delete('xf_change_log', "content_type LIKE 'dbtech_shop_%'");
		$this->db()->delete('xf_change_log', "field LIKE 'dbtech_shop_%'");
	}
}