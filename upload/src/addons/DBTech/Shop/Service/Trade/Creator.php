<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;

/**
 * Class Creator
 *
 * @package DBTech\Shop\Service\Item
 */
class Creator extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;
	
	/**
	 * @var Trade
	 */
	protected $trade;
	
	protected $alert = false;
	protected $alertReason = '';
	
	/**
	 * Create constructor.
	 *
	 * @param \XF\App $app
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(\XF\App $app)
	{
		parent::__construct($app);
		$this->setupDefaults();
	}
	
	/**
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function setupDefaults()
	{
		/** @var \DBTech\Shop\Entity\Trade trade */
		$trade = $this->em()->create('DBTech\Shop:Trade');
		
		$visitor = \XF::visitor();
		$trade->creator_user_id = $visitor->user_id;
		$trade->creator_username = $visitor->username;
		
		$trade->hydrateRelation('Creator', $visitor);
		
		$this->setTrade($trade);
	}
	
	/**
	 * @return Trade
	 */
	public function getTrade(): Trade
	{
		return $this->trade;
	}
	
	/**
	 * @param Trade $trade
	 */
	public function setTrade(Trade $trade)
	{
		$this->trade = $trade;
	}
	
	public function setRecipient(\XF\Entity\User $recipient)
	{
		$trade = $this->trade;
		
		$trade->recipient_user_id = $recipient->user_id;
		$trade->recipient_username = $recipient->username;
		
		$trade->hydrateRelation('Recipient', $recipient);
	}
	
	/**
	 * @param $alert
	 * @param null $reason
	 */
	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	protected function finalSetup()
	{
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
		return $trade->getErrors();
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

		$this->beforeInsert();

		$trade->save(true, false);

		$this->afterInsert();

		$db->commit();

		return $trade;
	}

	public function beforeInsert()
	{
	}
	
	/**
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function afterInsert()
	{
		$trade = $this->trade;
		
		if ($this->alert)
		{
			$extra = [
				'reason' => $this->alertReason,
			];
			
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->repository('XF:UserAlert');
			$alertRepo->alert(
				$trade->Recipient,
				$trade->creator_user_id,
				$trade->creator_username,
				'dbtech_shop_trade',
				$trade->trade_id,
				'invite',
				$extra
			);
		}
	}
}