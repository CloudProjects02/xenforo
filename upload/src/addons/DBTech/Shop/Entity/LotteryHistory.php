<?php

namespace DBTech\Shop\Entity;

use XF\Entity\LinkableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $lottery_history_id
 * @property int $lottery_id
 * @property array $drawn_numbers
 * @property int $draw_date
 * @property int $tickets_sold
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Currency $Currency
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\LotteryTicket[] $Tickets
 * @property \DBTech\Shop\Entity\Lottery $Lottery
 */
class LotteryHistory extends Entity implements LinkableInterface
{
	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return mixed|string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/lotteries/view-draw' : 'dbtech-shop/lotteries/view-draw';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
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
	 * @return string|\XF\Phrase
	 */
	public function getContentTitle(string $context = '')
	{
		if ($this->Lottery)
		{
			return \XF::phrase('dbtech_shop_history_for_lottery_x', ['drive' => $this->Lottery->title]);
		}

		return \XF::phrase('dbtech_shop_history_for_lottery_x', ['drive' => 'N/A']);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
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
			'tickets_sold' 			=> ['type' => self::UINT, 'default' => 0]
		];
		$structure->relations = [
			'Currency' => [
				'entity' => 'DBTech\Shop:Currency',
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true
			],
			'Tickets' => [
				'entity' => 'DBTech\Shop:LotteryTicket',
				'type' => self::TO_MANY,
				'conditions' => [
					['lottery_id', '=', '$lottery_id'],
					['draw_date', '=', '$draw_date']
				],
			],
			'Lottery' => [
				'entity' => 'DBTech\Shop:Lottery',
				'type' => self::TO_ONE,
				'conditions' => 'lottery_id',
				'primary' => true
			],
		];

		return $structure;
	}
}