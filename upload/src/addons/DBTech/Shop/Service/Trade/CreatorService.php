<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Trade $trade;
	protected bool $alert = false;
	protected string $alertReason = '';

	/**
	 * @param App $app
	 */
	public function __construct(App $app)
	{
		parent::__construct($app);
		$this->setupDefaults();
	}

	/**
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function setupDefaults(): void
	{
		$trade = \XF::app()->em()->create(Trade::class);

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
	public function setTrade(Trade $trade): void
	{
		$this->trade = $trade;
	}

	public function setRecipient(User $recipient): void
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
	public function setSendAlert($alert, $reason = null): void
	{
		$this->alert = (bool) $alert;
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
	public function afterInsert(): void
	{
		$trade = $this->trade;

		if ($this->alert)
		{
			$extra = [
				'reason' => $this->alertReason,
			];

			$alertRepo = \XF::app()->repository(UserAlertRepository::class);
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