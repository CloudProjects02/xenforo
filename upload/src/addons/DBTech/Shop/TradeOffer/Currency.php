<?php

namespace DBTech\Shop\TradeOffer;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;

/**
 * Class Currency
 *
 * @package DBTech\Shop\TradeOffer
 */
class Currency extends AbstractHandler
{
	/**
	 * @return \XF\Phrase
	 */
	public function getTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_currencies');
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
		$currencies = \XF::repository('DBTech\Shop:Currency')
			->getCurrencies(true)
			->filter(function (\DBTech\Shop\Entity\Currency $currency): ?\DBTech\Shop\Entity\Currency
			{
				if (!$currency->canTrade())
				{
					return null;
				}
				
				return $currency;
			})
		;
		
		$offers = $offers !== null
			? $offers
			: \XF::repository('DBTech\Shop:Trade')->getGroupedOffersFromTrade($trade)
		;
		
		return [
			'currencies' => $currencies,
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
		/** @var \DBTech\Shop\Entity\Currency $currency */
		$currency = $tradeOffer->getContent();
		
		if (!$currency->canTrade())
		{
			if ($tradeOffer->user_id == \XF::visitor()->user_id)
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_you_cannot_trade_currency_x', [
					'currency' => $currency->title
				]);
			}
			else
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_other_user_cannot_trade_currency');
			}
			return false;
		}
		
		if ($currency->getValueFromUser($tradeOffer->User, false) < $tradeOffer->quantity)
		{
			if ($tradeOffer->user_id == \XF::visitor()->user_id)
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_not_enough_to_trade_max_x', [
					'currency' => $currency->prefix . $currency->getValueFromUser($tradeOffer->User) . $currency->suffix . ' ' . $currency->title
				]);
			}
			else
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_other_user_not_enough_to_trade');
			}
			
			return false;
		}
		return true;
	}
	
	/**
	 * @param TradeOffer $tradeOffer
	 *
	 * @return bool
	 * @throws \XF\PrintableException
	 */
	public function finalize(TradeOffer $tradeOffer): bool
	{
		if ($tradeOffer->finalized)
		{
			return false;
		}
		
		/** @var \DBTech\Shop\Repository\Currency $currencyRepo */
		$currencyRepo = \XF::repository('DBTech\Shop:Currency');
		
		/** @var \DBTech\Shop\Entity\Currency $currency */
		$currency = $tradeOffer->getContent();
		
		/** @var \DBTech\Shop\XF\Entity\User $recipient */
		$recipient = $tradeOffer->user_id == $tradeOffer->Trade->creator_user_id
			? $tradeOffer->Trade->Recipient
			: $tradeOffer->Trade->Creator
		;
		
		// Remove currency from person making the offer
		$currencyRepo->removeCurrencyAmount(
			$currency,
			'trade',
			$tradeOffer->quantity,
			$tradeOffer->User,
			'dbtech_shop_trade',
			$tradeOffer->trade_id
		);
		
		// Add currency to person receiving the offer
		$currencyRepo->addCurrencyAmount(
			$currency,
			'trade',
			$tradeOffer->quantity,
			$recipient,
			'dbtech_shop_trade',
			$tradeOffer->trade_id
		);
		
		return true;
	}
}