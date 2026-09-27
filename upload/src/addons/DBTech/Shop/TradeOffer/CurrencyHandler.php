<?php

namespace DBTech\Shop\TradeOffer;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\Repository\TradeRepository;
use DBTech\Shop\XF\Entity\User;
use XF\Phrase;
use XF\PrintableException;

class CurrencyHandler extends AbstractHandler
{
	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
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
	public function getModifyTemplateData(Trade $trade, ?array $offers = null): array
	{
		$currencies = \XF::app()->repository(CurrencyRepository::class)
			->getCurrencies(true)
			->filter(function (Currency $currency): ?Currency
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
			: \XF::app()->repository(TradeRepository::class)->getGroupedOffersFromTrade($trade)
		;

		return [
			'currencies' => $currencies,
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
		/** @var Currency $currency */
		$currency = $tradeOffer->getContent();

		if (!$currency->canTrade())
		{
			if ($tradeOffer->user_id == \XF::visitor()->user_id)
			{
				$errors[] = \XF::phraseDeferred('dbtech_shop_you_cannot_trade_currency_x', [
					'currency' => $currency->title,
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
					'currency' => $currency->prefix . $currency->getValueFromUser($tradeOffer->User) . $currency->suffix . ' ' . $currency->title,
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
	 * @throws PrintableException
	 */
	public function finalize(TradeOffer $tradeOffer): bool
	{
		if ($tradeOffer->finalized)
		{
			return false;
		}

		$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

		/** @var Currency $currency */
		$currency = $tradeOffer->getContent();

		/** @var User $recipient */
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