<?php

namespace DBTech\Shop\TradeOffer;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;
use XF\Mvc\Entity\ArrayCollection;

/**
 * Class Purchase
 *
 * @package DBTech\Shop\TradeOffer
 */
class Purchase extends AbstractHandler
{
	/**
	 * @return \XF\Phrase
	 */
	public function getTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_items');
	}
	
	/**
	 * @param Trade $trade
	 * @param array|null $offers
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getModifyTemplateData(Trade $trade, array $offers = null): array
	{
		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = \XF::repository('DBTech\Shop:Purchase')
			->getViewablePurchasesForUser(\XF::visitor())
			->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
			{
				if (!$purchase->canTrade())
				{
					return null;
				}
				
				return $purchase;
			})
		;
		
		$offers = $offers !== null
			? $offers
			: \XF::repository('DBTech\Shop:Trade')->getGroupedOffersFromTrade($trade)
		;
		
		return [
			'purchases' => $purchases,
			'offers' => $offers
		];
	}
	
	/**
	 * @param TradeOffer $tradeOffer
	 * @param array $errors
	 *
	 * @return bool
	 */
	public function isValid(TradeOffer $tradeOffer, &$errors = []): bool
	{
		/** @var \DBTech\Shop\Entity\Purchase $purchase */
		$purchase = $tradeOffer->getContent();

		/** @noinspection PhpStatementHasEmptyBodyInspection */
		if ($tradeOffer->user_id != $purchase->user_id);
		{
			if ($tradeOffer->user_id == \XF::visitor()->user_id)
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_you_cannot_trade_item_x_you_are_not_owner', [
					'item' => $purchase->Item->title,
					'purchase' => $purchase->purchase_id
				]);
			}
			else
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_other_user_not_owner_of_item_x', [
					'item' => $purchase->Item->title
				]);
			}
		}
		
		/** @var \DBTech\Shop\XF\Entity\User $recipient */
		$recipient = $tradeOffer->user_id == $tradeOffer->Trade->creator_user_id
			? $tradeOffer->Trade->Recipient
			: $tradeOffer->Trade->Creator
		;
		
		/** @var \DBTech\Shop\Service\Purchase\Transfer $purchaseService */
		$purchaseService = \XF::app()->service('DBTech\Shop:Purchase\Transfer', $purchase, $recipient);
		$purchaseService->setIsTrade(true);
		
		if (!$purchaseService->validate($errors))
		{
			return false;
		}
		
		$purchase->reset();
		
		return true;
	}
	
	/**
	 * @param TradeOffer $tradeOffer
	 *
	 * @return bool
	 */
	public function finalize(TradeOffer $tradeOffer): bool
	{
		if ($tradeOffer->finalized)
		{
			return false;
		}
		
		/** @var \DBTech\Shop\Entity\Purchase $purchase */
		$purchase = $tradeOffer->getContent();
		
		/** @var \DBTech\Shop\XF\Entity\User $recipient */
		$recipient = $tradeOffer->user_id == $tradeOffer->Trade->creator_user_id
			? $tradeOffer->Trade->Recipient
			: $tradeOffer->Trade->Creator
		;
		
		/** @var \DBTech\Shop\Service\Purchase\Transfer $purchaseService */
		$purchaseService = \XF::app()->service('DBTech\Shop:Purchase\Transfer', $purchase, $recipient);
		$purchaseService->setIsTrade(true);
		$purchaseService->logIp(false);
		
		if (!$purchaseService->validate($errors))
		{
			return false;
		}
		
		$purchaseService->save();
		
		return true;
	}
}