<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Trade
 * @package DBTech\Shop\Repository
 */
class Trade extends Repository
{
	/** @var array */
	protected $handlerCache = [];
	
	
	/**
	 * @return Finder
	 */
	public function findPendingTradesForList(): Finder
	{
		return $this->finder('DBTech\Shop:Trade')
			->where('trade_state', ['pending', 'open', 'awaiting_accept'])
			->order('updated_date', 'DESC');
	}
	
	/**
	 * @return Finder
	 */
	public function findParticipatingPendingTrades(): Finder
	{
		$visitor = \XF::visitor();
		
		return $this->findPendingTradesForList()
			->whereOr(
				['creator_user_id', $visitor->user_id],
				['recipient_user_id', $visitor->user_id]
			);
	}
	
	/**
	 * @return Finder
	 */
	public function findCompletedTradesForList(): Finder
	{
		return $this->finder('DBTech\Shop:Trade')
			->where('trade_state', ['accepted', 'cancelled'])
			->order('updated_date', 'DESC');
	}
	
	/**
	 * @return Finder
	 */
	public function findParticipatingCompletedTrades(): Finder
	{
		$visitor = \XF::visitor();
		
		return $this->findCompletedTradesForList()
			->whereOr(
				['creator_user_id', $visitor->user_id],
				['recipient_user_id', $visitor->user_id]
			);
	}
	
	/**
	 * @param string $contentType
	 *
	 * @return \XF\Phrase
	 * @throws \Exception
	 */
	public function getContentTypeTitle(string $contentType): \XF\Phrase
	{
		$handler = $this->getTradeOfferHandler($contentType);
		return $handler ? $handler->getTitle() : \XF::phrase('unknown');
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getGroupedOffersFromTrade(\DBTech\Shop\Entity\Trade $trade): array
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		$offers = [];
		
		/** @var \DBTech\Shop\Entity\TradeOffer[]|\XF\Mvc\Entity\ArrayCollection $offersByContentType */
		$offersByContentType = $trade->Offers;
		$offersByContentType = $offersByContentType->filter(
			function (\DBTech\Shop\Entity\TradeOffer $tradeOffer) use ($visitor): ?\DBTech\Shop\Entity\TradeOffer
			{
				if ($tradeOffer->user_id != $visitor->user_id)
				{
					return null;
				}
			
				return $tradeOffer;
			}
		);
		$offersByContentType = $offersByContentType->groupBy('content_type', 'content_id');
		
		$handlers = $this->getTradeOfferHandlers();
		foreach ($handlers as $contentType => $handler)
		{
			$offers[$contentType] = [];
			
			if (empty($offersByContentType[$contentType]))
			{
				continue;
			}
			
			/** @var \DBTech\Shop\Entity\TradeOffer $offer */
			foreach ($offersByContentType[$contentType] as $offer)
			{
				$offers[$contentType][$offer->content_id] = $offer->quantity;
			}
		}
		
		return $offers;
	}
	
	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return \DBTech\Shop\TradeOffer\AbstractHandler|null
	 * @throws \Exception
	 */
	public function getTradeOfferHandler(string $type, bool $throw = false): ?\DBTech\Shop\TradeOffer\AbstractHandler
	{
		if (isset($this->handlerCache[$type]))
		{
			return $this->handlerCache[$type];
		}
		
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'dbtech_shop_trade_offer_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No trade offer handler for '$type'");
			}
			return null;
		}
		
		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Trade offer handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}
		
		$handlerClass = \XF::extendClass($handlerClass);
		$handler = new $handlerClass($type);
		
		$this->handlerCache[$type] = $handler;
		
		return $handler;
	}
	
	/**
	 * @return \DBTech\Shop\ItemType\AbstractHandler[]
	 * @throws \Exception
	 */
	public function getTradeOfferHandlers(): array
	{
		$handlers = [];
		
		foreach (\XF::app()->getContentTypeField('dbtech_shop_trade_offer_handler_class') AS $contentType => $handlerClass)
		{
			if (class_exists($handlerClass))
			{
				$handlerClass = \XF::extendClass($handlerClass);
				$handlers[$contentType] = new $handlerClass($contentType);
			}
		}
		
		return $handlers;
	}
}