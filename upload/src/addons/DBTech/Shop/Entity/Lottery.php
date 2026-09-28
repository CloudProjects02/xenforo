<?php

namespace DBTech\Shop\Entity;

use XF\Entity\LinkableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $lottery_id
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property float $ticket_price
 * @property int $currency_id
 * @property array $numbers
 * @property int $draw_interval_days
 * @property int $next_draw_date
 * @property int $previous_draw_date
 * @property array $prizes
 * @property array $drawn_numbers
 * @property int $tickets_sold
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Currency $Currency
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\LotteryPrizeMap[] $PrizeMap
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\LotteryTicket[] $CurrentTickets
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\LotteryTicket[] $AllTickets
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\LotteryHistory[] $History
 */
class Lottery extends Entity implements LinkableInterface
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
	 * @return bool
	 */
	public function canBuyTicket(): bool
	{
		return ($this->canView()
			&& $this->Currency->getValueFromUser(null, false) >= $this->ticket_price
			&& $this->next_draw_date > \XF::$time
		);
	}

	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return mixed|string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/lotteries' : 'dbtech-shop/lotteries';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/lotteries';
	}

	/**
	 * @param string $context
	 *
	 * @return string|\XF\Phrase
	 */
	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('dbtech_shop_lottery_x', ['title' => $this->title]);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_lottery';
		$structure->shortName = 'DBTech\Shop:Lottery';
		$structure->primaryKey = 'lottery_id';
		$structure->columns = [
			'lottery_id'         => ['type' => self::UINT, 'autoIncrement' => true],
			'title'              => ['type' => self::STR, 'required' => true],
			'description'        => ['type' => self::STR, 'default' => ''],
			'active'             => ['type' => self::BOOL, 'default' => true],
			'ticket_price'       => ['type' => self::FLOAT, 'default' => 50, 'min' => 0],
			'currency_id'        => ['type' => self::UINT, 'required' => true],
			'numbers'            => ['type' => self::JSON_ARRAY, 'default' => []],
			'draw_interval_days' => ['type' => self::UINT, 'default' => 7],
			'next_draw_date'     => ['type' => self::UINT, 'required' => true],
			'previous_draw_date' => ['type' => self::UINT, 'default' => 0],
			'prizes'             => ['type' => self::JSON_ARRAY, 'default' => []],
			'drawn_numbers'      => ['type' => self::JSON_ARRAY, 'default' => []],
			'tickets_sold'       => ['type' => self::UINT, 'default' => 0]
		];
		$structure->relations = [
			'Currency' => [
				'entity' => 'DBTech\Shop:Currency',
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true
			],
			'PrizeMap' => [
				'entity' => 'DBTech\Shop:LotteryPrizeMap',
				'type' => self::TO_MANY,
				'conditions' => 'lottery_id',
				'with' => ['Prize', 'Currency'],
				'cascadeDelete' => true
			],
			'CurrentTickets' => [
				'entity' => 'DBTech\Shop:LotteryTicket',
				'type' => self::TO_MANY,
				'conditions' => [
					['lottery_id', '=', '$lottery_id'],
					['draw_date', '=', '$next_draw_date']
				]
			],
			'AllTickets' => [
				'entity' => 'DBTech\Shop:LotteryTicket',
				'type' => self::TO_MANY,
				'conditions' => 'lottery_id',
				'cascadeDelete' => true
			],
			'History' => [
				'entity' => 'DBTech\Shop:LotteryHistory',
				'type' => self::TO_MANY,
				'conditions' => 'lottery_id',
				'cascadeDelete' => true
			]
		];

		return $structure;
	}
	
	/**
	 *
	 */
	protected function _setupDefaults()
	{
		$this->active = true;
		$this->ticket_price = 50;
		$this->numbers = [
			'main'	=> 7,
			'bonus'	=> 2,
			'total'	=> 34,
		];
	}
}