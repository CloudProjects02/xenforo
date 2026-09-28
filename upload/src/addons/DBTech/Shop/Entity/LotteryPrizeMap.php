<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $lottery_id
 * @property int $lottery_prize_id
 * @property int $currency_id
 * @property float $prize_amount
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Lottery $Lottery
 * @property \DBTech\Shop\Entity\LotteryPrize $Prize
 * @property \DBTech\Shop\Entity\Currency $Currency
 */
class LotteryPrizeMap extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_lottery_prize_map';
		$structure->shortName = 'DBTech\Shop:LotteryPrizeMap';
		$structure->primaryKey = ['lottery_id', 'lottery_prize_id'];
		$structure->columns = [
			'lottery_id'	=> ['type' => self::UINT, 'required' => true],
			'lottery_prize_id'	=> ['type' => self::UINT, 'required' => true],
			'currency_id'	=> ['type' => self::UINT, 'required' => true],
			'prize_amount'	=> ['type' => self::FLOAT, 'default' => 0, 'min' => 0],
		];
		$structure->relations = [
			'Lottery' => [
				'entity' => 'DBTech\Shop:Lottery',
				'type' => self::TO_ONE,
				'conditions' => 'lottery_id',
				'primary' => true
			],
			'Prize' => [
				'entity' => 'DBTech\Shop:LotteryPrize',
				'type' => self::TO_ONE,
				'conditions' => 'lottery_prize_id',
				'primary' => true
			],
			'Currency' => [
				'entity' => 'DBTech\Shop:Currency',
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true
			]
		];

		return $structure;
	}
}