<?php

namespace DBTech\Shop\Entity;

use XF\Entity\LinkableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int $lottery_history_id
 * @property int $lottery_id
 * @property array $drawn_numbers
 * @property int $draw_date
 * @property int $tickets_sold
 *
 * RELATIONS
 * @property-read Currency|null $Currency
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\LotteryTicket> $Tickets
 * @property-read Lottery|null $Lottery
 */
class LotteryHistory extends Entity implements LinkableInterface
{
	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/lotteries/view-draw' : 'dbtech-shop/lotteries/view-draw';
		return \XF::app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/lotteries/view-draw';
	}

	/**
	 * @param string $context
	 *
	 * @return Phrase
	 */
	public function getContentTitle(string $context = ''): Phrase
	{
		if ($this->Lottery)
		{
			return \XF::phrase('dbtech_shop_history_for_lottery_x', ['drive' => $this->Lottery->title]);
		}

		return \XF::phrase('dbtech_shop_history_for_lottery_x', ['drive' => 'N/A']);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_lottery_history';
		$structure->shortName = 'DBTech\Shop:LotteryHistory';
		$structure->primaryKey = 'lottery_history_id';
		$structure->columns = [
			'lottery_history_id'	=> ['type' => self::UINT, 'autoIncrement' => true],
			'lottery_id' 			=> ['type' => self::UINT, 'required' => true],
			'drawn_numbers' 		=> ['type' => self::JSON_ARRAY, 'default' => []],
			'draw_date' 			=> ['type' => self::UINT, 'required' => true],
			'tickets_sold' 			=> ['type' => self::UINT, 'default' => 0],
		];
		$structure->relations = [
			'Currency' => [
				'entity' => Currency::class,
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true,
			],
			'Tickets' => [
				'entity' => LotteryTicket::class,
				'type' => self::TO_MANY,
				'conditions' => [
					['lottery_id', '=', '$lottery_id'],
					['draw_date', '=', '$draw_date'],
				],
			],
			'Lottery' => [
				'entity' => Lottery::class,
				'type' => self::TO_ONE,
				'conditions' => 'lottery_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}