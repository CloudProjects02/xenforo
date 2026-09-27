<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;
use DBTech\Shop\Finder\TradeFinder;
use DBTech\Shop\TradeOffer\AbstractHandler;
use DBTech\Shop\XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\Phrase;

class TradeRepository extends Repository
{
	protected array $handlerCache = [];


	/**
	 * @return TradeFinder
	 */
	public function findPendingTradesForList(): TradeFinder
	{
		return \XF::app()->finder(TradeFinder::class)
			->where('trade_state', ['pending', 'open', 'awaiting_accept'])
			->order('updated_date', 'DESC');
	}

	/**
	 * @return TradeFinder
	 */
	public function findParticipatingPendingTrades(): TradeFinder
	{
		$visitor = \XF::visitor();

		return $this->findPendingTradesForList()
			->whereOr(
				['creator_user_id', $visitor->user_id],
				['recipient_user_id', $visitor->user_id]
			);
	}

	/**
	 * @return TradeFinder
	 */
	public function findCompletedTradesForList(): TradeFinder
	{
		return \XF::app()->finder(TradeFinder::class)
			->where('trade_state', ['accepted', 'cancelled'])
			->order('updated_date', 'DESC');
	}

	/**
	 * @return TradeFinder
	 */
	public function findParticipatingCompletedTrades(): TradeFinder
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
	 * @return Phrase
	 * @throws \Exception
	 */
	public function getContentTypeTitle(string $contentType): Phrase
	{
		$handler = $this->getTradeOfferHandler($contentType);
		return $handler ? $handler->getTitle() : \XF::phrase('unknown');
	}

	/**
	 * @param Trade $trade
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getGroupedOffersFromTrade(Trade $trade): array
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		$offers = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradeOffer> $offersByContentType */
		$offersByContentType = $trade->Offers;
		$offersByContentType = $offersByContentType->filter(
			function (TradeOffer $tradeOffer) use ($visitor): ?TradeOffer
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
		foreach ($handlers AS $contentType => $handler)
		{
			$offers[$contentType] = [];

			if (empty($offersByContentType[$contentType]))
			{
				continue;
			}

			/** @var TradeOffer $offer */
			foreach ($offersByContentType[$contentType] AS $offer)
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
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	public function getTradeOfferHandler(string $type, bool $throw = false): ?AbstractHandler
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