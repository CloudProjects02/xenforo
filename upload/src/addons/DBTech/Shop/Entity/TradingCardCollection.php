<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $trading_card_id
 * @property int $user_id
 * @property bool $trade_locked
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\TradingCard $Card
 * @property \XF\Entity\User $User
 */
class TradingCardCollection extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_trading_card_collection';
		$structure->shortName = 'DBTech\Shop:TradingCardCollection';
		$structure->primaryKey = ['trading_card_id', 'user_id'];
		$structure->columns = [
			'trading_card_id'	=> ['type' => self::UINT, 'required' => true],
			'user_id'			=> ['type' => self::UINT, 'required' => true],
			'trade_locked' 		=> ['type' => self::BOOL, 'default' => false],
		];
		$structure->relations = [
			'Card' => [
				'entity' => 'DBTech\Shop:TradingCard',
				'type' => self::TO_ONE,
				'conditions' => 'trading_card_id',
				'primary' => true,
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];

		return $structure;
	}
}