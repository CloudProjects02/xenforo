<?php

namespace DBTech\Shop\Entity;

use XF\Entity\ViewableInterface;
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
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\LotteryTicket> $WinningTickets
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\LotteryPrizeMap> $PrizeMap
 */
class LotteryPrize extends Entity implements ViewableInterface
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
	 * @param Structure $structure
	 *
	 * @return Structure
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
				'entity' => LotteryTicket::class,
				'type' => self::TO_MANY,
				'conditions' => [
					['lottery_prize_id', '=', '$lottery_prize_id'],
				],
				'cascadeDelete' => true,
			],
			'PrizeMap' => [
				'entity' => LotteryPrizeMap::class,
				'type' => self::TO_MANY,
				'conditions' => 'lottery_prize_id',
				'cascadeDelete' => true,
			],
		];

		return $structure;
	}
}