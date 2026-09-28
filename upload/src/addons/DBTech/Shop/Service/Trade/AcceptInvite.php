<?php

namespace DBTech\Shop\Service\Trade;

use DBTech\Shop\Entity\Trade;

/**
 * Class AcceptInvite
 *
 * @package DBTech\Shop\Service\Item
 */
class AcceptInvite extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;
	
	/**
	 * @var Trade
	 */
	protected $trade;
	
	protected $alert = false;
	
	
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
		$this->trade->trade_state = 'open';
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
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->repository('XF:UserAlert');
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