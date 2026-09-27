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
 * @property-read Lottery|null $Lottery
 * @property-read LotteryPrize|null $Prize
 * @property-read Currency|null $Currency
 */
class LotteryPrizeMap extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
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
				'entity' => Lottery::class,
				'type' => self::TO_ONE,
				'conditions' => 'lottery_id',
				'primary' => true,
			],
			'Prize' => [
				'entity' => LotteryPrize::class,
				'type' => self::TO_ONE,
				'conditions' => 'lottery_prize_id',
				'primary' => true,
			],
			'Currency' => [
				'entity' => Currency::class,
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}