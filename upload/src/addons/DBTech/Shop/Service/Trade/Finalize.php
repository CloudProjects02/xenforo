<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;

/**
 * Class Finalize
 *
 * @package DBTech\Shop\Service\Item
 */
class Finalize extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;
	
	/**
	 * @var Trade
	 */
	protected $trade;
	
	protected $alert = false;
	
	
	/**
	 * Accept constructor.
	 *
	 * @param \XF\App $app
	 * @param Trade $trade
	 */
	public function __construct(\XF\App $app, Trade $trade)
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
	 * @param $alert
	 */
	public function setSendAlert($alert)
	{
		$this->alert = (bool)$alert;
	}
	
	protected function finalSetup()
	{
		$trade = $this->trade;
		
		$trade->trade_state = 'accepted';
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		/** @var \DBTech\Shop\Entity\Trade $trade */
		$trade = $this->trade;

		$trade->preSave();
		$errors = $trade->getErrors();
		
		foreach ($trade->Offers as $tradeOffer)
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
		}

		return $errors;
	}
	
	/**
	 * @return \DBTech\Shop\Entity\Trade
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function _save(): Trade
	{
		$trade = $this->trade;

		$db = $this->db();
		$db->beginTransaction();

		$this->beforeUpdate();
		
		$trade->save(true, false);
		
		foreach ($trade->Offers as $tradeOffer)
		{
			if ($tradeOffer->finalize())
			{
				$tradeOffer->save(true, false);
			}
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
	public function afterUpdate()
	{
		$trade = $this->trade;
		
		if ($this->alert)
		{
			$visitor = \XF::visitor();
			
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->repository('XF:UserAlert');
			$alertRepo->fastDeleteAlertsToUser(
				$trade->recipient_user_id == $visitor->user_id ? $trade->creator_user_id : $trade->recipient_user_id,
				'dbtech_shop_trade',
				$trade->trade_id,
				'modify'
			);
			$alertRepo->alert(
				$trade->recipient_user_id == $visitor->user_id ? $trade->Creator : $trade->Recipient,
				0,
				'',
				'dbtech_shop_trade',
				$trade->trade_id,
				'finalize'
			);
		}
	}
}