<?php

namespace DBTech\Security\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Schema\Alter;
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
		$this->db()->delete('xf_change_log', "content_type LIKE 'dbtech_security_%'");
		$this->db()->delete('xf_change_log', "field LIKE 'dbtech_security_%'");

		$this->db()->delete('xf_tfa_provider', "provider_id = 'dbtech_security_authn'");

		$contentTypesQuoted = $this->db()->quote([
			'dbtech_security_tor',
			'dbtech_security_country',
		]);
		$this->db()->delete('xf_ip_match', 'match_type IN (' . $contentTypesQuoted . ')');
	}

	/**
	 *
	 */
	public function uninstallStep4(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_ip_match', function (Alter $table)
		{
			$table->changeColumn('match_type')->removeValues(['dbtech_security_tor', 'dbtech_security_country']);
		});
	}
}