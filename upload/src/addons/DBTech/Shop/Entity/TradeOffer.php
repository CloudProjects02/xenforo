<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $trade_id
 * @property int $user_id
 * @property string $content_type
 * @property int $content_id
 * @property int $quantity
 * @property bool $finalized
 *
 * GETTERS
 * @property Entity|null $Content
 * @property \DBTech\Shop\TradeOffer\AbstractHandler|null $Handler
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Trade $Trade
 * @property \XF\Entity\User $User
 */
class TradeOffer extends Entity
{
	/**
	 * @param array $errors
	 *
	 * @return bool
	 */
	public function isValid(&$errors = []): bool
	{
		$handler = $this->Handler;
		
		return ($handler && $handler->isValid($this, $errors));
	}
	
	/**
	 * @throws \LogicException
	 */
	public function finalize(): bool
	{
		if ($this->finalized)
		{
			// Ensure this doesn't happen multiple times
			return false;
		}
		
		$handler = $this->Handler;
		if (!$handler)
		{
			throw new \LogicException("Handler for content type {$this->content_type} could not be loaded.");
		}
		
		if ($handler->finalize($this))
		{
			$this->finalized = true;
			return true;
		}
		
		return false;
	}
	
	/**
	 * @return Entity|null
	 */
	public function getContent(): ?Entity
	{
		$handler = $this->Handler;
		return $handler ? $handler->getContent($this->content_id) : null;
	}
	
	/**
	 * @param Entity|null $content
	 */
	public function setContent(Entity $content = null)
	{
		$this->_getterCache['Content'] = $content;
	}
	
	/**
	 * @return \DBTech\Shop\TradeOffer\AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandler(): ?\DBTech\Shop\TradeOffer\AbstractHandler
	{
		return $this->getTradeRepo()->getTradeOfferHandler($this->content_type);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_trade_offer';
		$structure->shortName = 'DBTech\Shop:TradeOffer';
		$structure->primaryKey = ['trade_id', 'user_id', 'content_type', 'content_id'];
		$structure->columns = [
			'trade_id'     => ['type' => self::UINT, 'required' => true],
			'user_id'      => ['type' => self::UINT, 'required' => true],
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id'   => ['type' => self::UINT, 'required' => true],
			'quantity'     => ['type' => self::UINT, 'default' => 1],
			'finalized'    => ['type' => self::BOOL, 'default' => false],
		];
		$structure->getters = [
			'Content' => true,
			'Handler' => true,
		];
		$structure->relations = [
			'Trade' => [
				'entity' => 'DBTech\Shop:Trade',
				'type' => self::TO_ONE,
				'conditions' => 'trade_id',
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
	
	/**
	 * @return \DBTech\Shop\Repository\Trade
	 */
	protected function getTradeRepo(): \DBTech\Shop\Repository\Trade
	{
		return $this->repository('DBTech\Shop:Trade');
	}
}