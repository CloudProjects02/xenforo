<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;
use XF\App;
use XF\PrintableException;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CancelService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Trade $trade;
	protected bool $alert = false;
	protected string $alertReason = '';
	protected string $previousState;


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
	protected function setupDefaults(): void
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
	public function setSendAlert($alert, $reason = null): void
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	/**
	 *
	 */
	protected function finalSetup(): void
	{
		$this->trade->trade_state = 'cancelled';
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
		return $trade->getErrors();
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
	public function afterUpdate(): void
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
			)
			{
				// Invite was pending and the current user is the recipient of the invite
				$action = 'invite_decline';
			}
			else
			{
				$action = 'cancel';
			}

			$alertRepo = \XF::app()->repository(UserAlertRepository::class);
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