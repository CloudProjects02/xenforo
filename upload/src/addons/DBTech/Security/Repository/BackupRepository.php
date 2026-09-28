<?php

namespace DBTech\Security\Repository;

use DBTech\Security\Entity\Snapshot;
use DBTech\Security\Finder\SnapshotFinder;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Repository\OptionRepository;

class BackupRepository extends Repository
{
	/**
	 * @return SnapshotFinder
	 */
	public function findSnapshotsForList(): SnapshotFinder
	{
		return \XF::app()->finder(SnapshotFinder::class)
			->order('dateline', 'DESC')
		;
	}

	/**
	 * @return int
	 * @throws PrintableException
	 */
	public function backupOptions(): int
	{
		$snapshot = \XF::app()->em()->create(Snapshot::class);
		$snapshot->data = \XF::app()->repository(OptionRepository::class)->getOptionCacheData();
		$snapshot->save();

		// Insert the settings backup
		$this->db()->delete('xf_dbtech_security_snapshot', 'dateline <= ?', \XF::$time - 604800);

		return $snapshot->snapshot_id;
	}

	/**
	 * @param array $data
	 */
	public function updateOptions(array $data): void
	{
		$db = $this->db();

		$db->beginTransaction();

		foreach ($data AS $key => $value)
		{
			$db->update('xf_option', [
				'option_value' => is_array($value) ? json_encode($value) : $value,
			], 'option_id = ?', $key);
		}

		$db->commit();
	}
}