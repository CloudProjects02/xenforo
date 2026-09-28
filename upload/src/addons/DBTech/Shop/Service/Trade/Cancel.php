<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;

/**
 * Class Cancel
 *
 * @package DBTech\Shop\Service\Item
 */
class Cancel extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;
	
	/**
	 * @var Trade
	 */
	protected $trade;
	
	protected $alert = false;
	protected $alertReason = '';
	
	protected $previousState;
	
	
	/**
	 * AcceptInvite constructor.
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
		$this->previousState = $this->trade->trade_state;
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
	
	/**
	 *
	 */
	protected function finalSetup()
	{
		$this->trade->trade_state = 'cancelled';
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

		$this->beforeUpdate();

		$trade->save(true, false);

		$this->afterUpdate();

		$db->commit();

		return $trade;
	}

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
			
			$extra = [
				'reason' => $this->alertReason,
			];
			
			if ($trade->recipient_user_id == $visitor->user_id
				&& $this->previousState == 'pending'
			) {
				// Invite was pending and the current user is the recipient of the invite
				$action = 'invite_decline';
			}
			else
			{
				$action = 'cancel';
			}
			
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->repository('XF:UserAlert');
			$alertRepo->alert(
				$trade->recipient_user_id == $visitor->user_id ? $trade->Creator : $trade->Recipient,
				$visitor->user_id,
				$visitor->username,
				'dbtech_shop_trade',
				$trade->trade_id,
				$action,
				$extra
			);
		}
	}
}