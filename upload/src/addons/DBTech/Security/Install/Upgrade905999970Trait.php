<?php

namespace DBTech\Security\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Schema\Column;
use XF\Db\SchemaManager;
use XF\Repository\IconRepository;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade905999970Trait
{
	/**
	 * @return void
	 */
	public function upgrade905000011Step1(): void
	{
		$db = $this->db();
		$db->beginTransaction();

		$db->delete('xf_tfa_provider', 'provider_id = ?', 'dbtech_security_authn');
		$db->delete('xf_user_tfa', 'provider_id = ?', 'dbtech_security_authn');

		$db->commit();
	}

	/**
	 * @return void
	 */
	public function upgrade905000032Step1(): void
	{
		$this->applyTables();
	}

	/**
	 * @param $previousVersion
	 * @param array $stateChanges
	 */
	protected function postUpgrade905000011($previousVersion, array &$stateChanges): void
	{
		$iconRepo = \XF::repository(IconRepository::class);
		$iconRepo->enqueueUsageAnalyzer('extra');
	}
}