<?php

namespace DBTech\Security\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $criteria_id
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property int $weight
 * @property string $fallback
 * @property int $day_limit
 * @property int $amount_limit
 * @property int $validate_input
 */
class Criteria extends Entity
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_criteria';
		$structure->shortName = 'DBTech\Security:Criteria';
		$structure->primaryKey = 'criteria_id';
		$structure->columns = [
			'criteria_id'		=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title' 			=> ['type' => self::STR, 'required' => true],
			'description' 		=> ['type' => self::STR, 'default' => ''],
			'active' 			=> ['type' => self::BOOL, 'default' => true],
			'weight' 			=> ['type' => self::UINT, 'default' => 10],
			'fallback' 			=> ['type' => self::STR, 'default' => 'skip'],
			'day_limit' 		=> ['type' => self::UINT, 'default' => 30],
			'amount_limit' 		=> ['type' => self::UINT, 'default' => 5],
			'validate_input' 	=> ['type' => self::UINT, 'default' => 1],
		];

		return $structure;
	}
}