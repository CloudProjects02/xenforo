<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;
use XF\App;
use XF\PrintableException;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Trade $trade;
	protected bool $alert = false;

	/** @var TradeOffer[] */
	protected array $tradeOffers = [];


	/**
	 * @param App $app
	 * @param Trade $trade
	 */
	public function __construct(App $app, Trade $trade)
	{
		parent::__construct($app);
		$this->trade = $trade;
		$this->setupDefaults();
	}

	/**
	 *
	 */
	protected function setupDefaults()
	{
	}

	/**
	 * @return Trade
	 */
	public function getTrade(): Trade
	{
		return $this->trade;
	}

	/**
	 * @param string $contentType
	 * @param int $contentId
	 * @param int $quantity
	 *
	 * @throws \InvalidArgumentException
	 */
	public function addOffer(string $contentType, int $contentId, int $quantity = 1): void
	{
		if ($quantity)
		{
			// Only add trade offers if the quantity is >= 1
			$this->tradeOffers[] = $this->trade->getNewTradeOffer(
				$contentType,
				$contentId,
				$quantity
			);
		}
	}


	/**
	 * @param $alert
	 */
	public function setSendAlert($alert): void
	{
		$this->alert = (bool) $alert;
	}

	protected function finalSetup(): void
	{
		$trade = $this->trade;

		if ($trade->trade_state != 'pending')
		{
			$trade->trade_state = 'open';
		}
		$trade->recipient_accepted = false;
		$trade->creator_accepted = false;
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		/** @var Trade $trade */
		$trade = $this->trade;

		$trade->preSave();
		$errors = $trade->getErrors();

		foreach ($this->tradeOffers AS $tradeOffer)
		{
			$offerErrors = [];
			if (!$tradeOffer->isValid($offerErrors))
			{
				if ($offerErrors)
				{
					$errors = array_merge($errors, $offerErrors);
				}
				else
				{
					$errors[] = \XF::phraseDeferred('dbtech_shop_cannot_offer_item');
				}
			}

			$tradeOffer->preSave();
			$errors = array_merge($errors, $tradeOffer->getErrors());
		}

		return $errors;
	}

	/**
	 * @return Trade
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function _save(): Trade
	{
		$trade = $this->trade;

		$db = $this->db();
		$db->beginTransaction();

		$this->beforeUpdate();

		$visitor = \XF::visitor();
		foreach ($this->trade->Offers AS $tradeOffer)
		{
			if ($tradeOffer->user_id == $visitor->user_id)
			{
				$tradeOffer->delete(true, false);
			}
		}

		$trade->save(true, false);

		foreach ($this->tradeOffers AS $tradeOffer)
		{
			$tradeOffer->save(true, false);
		}

		$this->afterUpdate();

		$db->commit();

		return $trade;
	}

	/**
	 *
	 */
	public function beforeUpdate()
	{
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function afterUpdate(): void
	{
		$trade = $this->trade;

		if ($this->alert)
		{
			$visitor = \XF::visitor();

			$alertRepo = \XF::app()->repository(UserAlertRepository::class);
			$alertRepo->fastDeleteAlertsToUser(
				$trade->recipient_user_id == $visitor->user_id ? $trade->creator_user_id : $trade->recipient_user_id,
				'dbtech_shop_trade',
				$trade->trade_id,
				'modify'
			);
			$alertRepo->alert(
				$trade->recipient_user_id == $visitor->user_id ? $trade->Creator : $trade->Recipient,
				$visitor->user_id,
				$visitor->username,
				'dbtech_shop_trade',
				$trade->trade_id,
				'modify'
			);
		}
	}
}