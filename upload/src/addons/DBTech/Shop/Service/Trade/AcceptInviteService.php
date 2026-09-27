<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;
use XF\App;
use XF\PrintableException;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class AcceptInviteService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Trade $trade;
	protected bool $alert = false;


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
	 * @param $alert
	 */
	public function setSendAlert($alert): void
	{
		$this->alert = (bool) $alert;
	}

	protected function finalSetup(): void
	{
		$this->trade->trade_state = 'open';
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
			$alertRepo = \XF::app()->repository(UserAlertRepository::class);
			$alertRepo->alert(
				$trade->Creator,
				$trade->recipient_user_id,
				$trade->recipient_user_id,
				'dbtech_shop_trade',
				$trade->trade_id,
				'invite_accept'
			);
		}
	}
}