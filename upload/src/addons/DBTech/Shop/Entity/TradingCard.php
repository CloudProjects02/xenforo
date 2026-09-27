<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $trading_card_id
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property int $rarity
 * @property string $filename
 *
 * RELATIONS
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradingCardCollection> $Collections
 */
class TradingCard extends Entity
{
	/**
	 * Undocumented function
	 *
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
		$structure->table = 'xf_dbtech_shop_trading_card';
		$structure->shortName = 'DBTech\Shop:TradingCard';
		$structure->primaryKey = 'trading_card_id';
		$structure->columns = [
			'trading_card_id'	=> ['type' => self::UINT, 'autoIncrement' => true],
			'title' 			=> ['type' => self::STR, 'required' => true],
			'description' 		=> ['type' => self::STR, 'default' => ''],
			'active' 			=> ['type' => self::BOOL, 'default' => true],
			'rarity' 			=> ['type' => self::UINT, 'default' => 1],
			'filename' 			=> ['type' => self::STR, 'required' => true],
		];
		$structure->relations = [
			'Collections' => [
				'entity' => TradingCardCollection::class,
				'type' => self::TO_MANY,
				'conditions' => 'trading_card_id',
			],
		];

		return $structure;
	}
}