<?php

namespace DBTech\Shop\TradeOffer;

use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;

abstract class AbstractHandler
{
	protected string $contentType;


	/**
	 * @param string $contentType
	 */
	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;
	}

	abstract public function getTitle();
	abstract public function getModifyTemplateData(Trade $trade, ?array $offers = null);
	abstract public function isValid(TradeOffer $tradeOffer, array &$errors = []);
	abstract public function finalize(TradeOffer $tradeOffer);


	/**
	 * @return string
	 */
	public function getTemplateName(): string
	{
		return 'public:dbtech_shop_trade_offer_' . $this->contentType;
	}

	/**
	 * @param TradeOffer $tradeOffer
	 *
	 * @return array
	 */
	public function getTemplateData(TradeOffer $tradeOffer): array
	{
		return [
			'tradeOffer' => $tradeOffer,
			'trade' => $tradeOffer->Trade,
			'content' => $tradeOffer->Content,
		];
	}

	/**
	 * @param TradeOffer $tradeOffer
	 *
	 * @return string
	 */
	public function render(TradeOffer $tradeOffer): string
	{
		$template = $this->getTemplateName();
		if (!$template)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate($template, $this->getTemplateData($tradeOffer));
	}

	/**
	 * @return string
	 */
	public function getModifyTemplateName(): string
	{
		return 'public:dbtech_shop_trade_offer_modify_' . $this->contentType;
	}

	/**
	 * @param Trade $trade
	 * @param array|null $offers
	 *
	 * @return string
	 */
	public function renderModify(Trade $trade, ?array $offers = null): string
	{
		$template = $this->getModifyTemplateName();
		if (!$template)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate($template, $this->getModifyTemplateData($trade, $offers));
	}

	/**
	 * @return string
	 */
	public function getOfferTemplateName(): string
	{
		return 'public:dbtech_shop_inventory_purchase_view';
	}

	/**
	 * @param TradeOffer $tradeOffer
	 *
	 * @return array
	 */
	public function getOfferTemplateData(TradeOffer $tradeOffer): array
	{
		/** @var Purchase $purchase */
		$purchase = $tradeOffer->Content;

		return [
			'tradeOffer' => $tradeOffer,
			'trade' => $tradeOffer->Trade,

			'purchase' => $purchase,
			'item' => $purchase->Item,
		];
	}

	/**
	 * @param TradeOffer $tradeOffer
	 *
	 * @return string
	 */
	public function renderOffer(TradeOffer $tradeOffer): string
	{
		$template = $this->getOfferTemplateName();
		if (!$template)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate($template, $this->getOfferTemplateData($tradeOffer));
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return [];
	}

	/**
	 * @param $id
	 *
	 * @return ArrayCollection|Entity|null
	 */
	public function getContent($id): Entity|ArrayCollection|null
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
	}
}