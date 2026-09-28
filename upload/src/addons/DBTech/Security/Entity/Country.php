<?php

namespace DBTech\Security\Entity;

use DBTech\Security\Repository\BanningRepository;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $country_code
 * @property string $name
 * @property string $native_name
 * @property string $iso_code
 * @property bool $blocked
 */
class Country extends Entity
{
	/**
	 *
	 */
	protected function _postSave(): void
	{
		$blockChange = $this->isStateChanged('blocked', true);
		if ($blockChange == 'enter')
		{
			\XF::app()->jobManager()->enqueueUnique(
				'dbtechSecurityCountryBlockRebuild',
				'DBTech\Security:CountryBlockRebuild',
				[],
				false
			);
		}
		else if ($blockChange == 'leave')
		{
			\XF::app()->repository(BanningRepository::class)
				->removeBannedCountryIps($this->country_code)
			;
		}
	}

	/**
	 *
	 */
	protected function _postDelete(): void
	{
		if ($this->blocked)
		{
			\XF::app()->repository(BanningRepository::class)
				->removeBannedCountryIps($this->country_code)
			;
		}
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_country';
		$structure->shortName = 'DBTech\Security:Country';
		$structure->primaryKey = 'country_code';
		$structure->columns = [
			'country_code' => ['type' => self::STR, 'maxLength' => 2],
			'name' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
			'native_name' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
			'iso_code' => ['type' => self::STR, 'maxLength' => 3],
			'blocked' => ['type' => self::BOOL, 'default' => false],
		];
		$structure->behaviors = [];
		$structure->getters = [];
		$structure->relations = [];

		return $structure;
	}
}