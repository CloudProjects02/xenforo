<?php

namespace DBTech\Shop\TradeOffer;

use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;
use DBTech\Shop\Repository\PurchaseRepository;
use DBTech\Shop\Repository\TradeRepository;
use DBTech\Shop\Service\Purchase\TransferService;
use DBTech\Shop\XF\Entity\User;
use XF\Phrase;

class PurchaseHandler extends AbstractHandler
{
	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
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
	public function getModifyTemplateData(Trade $trade, ?array $offers = null): array
	{
		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)
			->getViewablePurchasesForUser(\XF::visitor())
			->filter(function (Purchase $purchase): ?Purchase
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
			: \XF::app()->repository(TradeRepository::class)->getGroupedOffersFromTrade($trade)
		;

		return [
			'purchases' => $purchases,
			'offers' => $offers,
		];
	}

	/**
	 * @param TradeOffer $tradeOffer
	 * @param array $errors
	 *
	 * @return bool
	 */
	public function isValid(TradeOffer $tradeOffer, array &$errors = []): bool
	{
		/** @var Purchase $purchase */
		$purchase = $tradeOffer->getContent();

		/** @noinspection PhpStatementHasEmptyBodyInspection */
		if ($tradeOffer->user_id != $purchase->user_id);
		{
			if ($tradeOffer->user_id == \XF::visitor()->user_id)
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_you_cannot_trade_item_x_you_are_not_owner', [
					'item' => $purchase->Item->title,
					'purchase' => $purchase->purchase_id,
				]);
			}
			else
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_other_user_not_owner_of_item_x', [
					'item' => $purchase->Item->title,
				]);
			}
		}

		/** @var User $recipient */
		$recipient = $tradeOffer->user_id == $tradeOffer->Trade->creator_user_id
			? $tradeOffer->Trade->Recipient
			: $tradeOffer->Trade->Creator
		;

		$purchaseService = \XF::app()->service(TransferService::class, $purchase, $recipient);
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

		/** @var Purchase $purchase */
		$purchase = $tradeOffer->getContent();

		/** @var User $recipient */
		$recipient = $tradeOffer->user_id == $tradeOffer->Trade->creator_user_id
			? $tradeOffer->Trade->Recipient
			: $tradeOffer->Trade->Creator
		;

		$purchaseService = \XF::app()->service(TransferService::class, $purchase, $recipient);
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