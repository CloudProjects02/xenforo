<?php

namespace DBTech\Security\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $snapshot_id
 * @property int $dateline
 * @property array $data
 *
 * GETTERS
 * @property-read string $title
 */
class Snapshot extends Entity
{
	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return \XF::language()->dateTime($this->dateline);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_snapshot';
		$structure->shortName = 'DBTech\Security:Snapshot';
		$structure->primaryKey = 'snapshot_id';
		$structure->columns = [
			'snapshot_id'		=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'dateline' 			=> ['type' => self::UINT, 'default' => \XF::$time],
			'data' 				=> ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->getters = [
			'title' => true,
		];

		return $structure;
	}
}