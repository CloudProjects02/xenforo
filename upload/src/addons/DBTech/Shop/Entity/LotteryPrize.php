<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $lottery_prize_id
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property array $numbers
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\LotteryTicket[] $WinningTickets
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\LotteryPrizeMap[] $PrizeMap
 */
class LotteryPrize extends Entity
{
	/**
	 * @return bool
	 */
	public function canView(): bool
	{
		return $this->isActive();
	}
	
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_lottery_prize';
		$structure->shortName = 'DBTech\Shop:LotteryPrize';
		$structure->primaryKey = 'lottery_prize_id';
		$structure->columns = [
			'lottery_prize_id'	=> ['type' => self::UINT, 'autoIncrement' => true],
			'title' 			=> ['type' => self::STR, 'required' => true],
			'description' 		=> ['type' => self::STR, 'default' => ''],
			'active' 			=> ['type' => self::BOOL, 'default' => true],
			'numbers' 			=> ['type' => self::JSON_ARRAY, 'required' => true],
		];
		$structure->relations = [
			'WinningTickets' => [
				'entity' => 'DBTech\Shop:LotteryTicket',
				'type' => self::TO_MANY,
				'conditions' => [
					['lottery_prize_id', '=', '$lottery_prize_id']
				],
				'cascadeDelete' => true
			],
			'PrizeMap' => [
				'entity' => 'DBTech\Shop:LotteryPrizeMap',
				'type' => self::TO_MANY,
				'conditions' => 'lottery_prize_id',
				'cascadeDelete' => true
			],
		];

		return $structure;
	}
}