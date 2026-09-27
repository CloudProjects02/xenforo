<?php

namespace DBTech\Shop\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $lottery_ticket_id
 * @property int $lottery_id
 * @property int $user_id
 * @property int $ticket_date
 * @property int $draw_date
 * @property array $numbers
 * @property int $lottery_prize_id
 *
 * RELATIONS
 * @property-read Lottery|null $Lottery
 * @property-read LotteryPrize|null $Prize
 * @property-read User|null $User
 */
class LotteryTicket extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_lottery_ticket';
		$structure->shortName = 'DBTech\Shop:LotteryTicket';
		$structure->primaryKey = 'lottery_ticket_id';
		$structure->columns = [
			'lottery_ticket_id'	=> ['type' => self::UINT, 'autoIncrement' => true],
			'lottery_id' 		=> ['type' => self::UINT, 'required' => true],
			'user_id' 			=> ['type' => self::UINT, 'required' => true],
			'ticket_date' 		=> ['type' => self::UINT, 'default' => \XF::$time],
			'draw_date' 		=> ['type' => self::UINT, 'required' => true],
			'numbers' 			=> ['type' => self::JSON_ARRAY, 'required' => true],
			'lottery_prize_id' 	=> ['type' => self::UINT, 'default' => 0],
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
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}