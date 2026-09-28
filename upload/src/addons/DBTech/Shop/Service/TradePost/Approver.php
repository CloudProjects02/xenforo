<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;

/**
 * Class Approver
 *
 * @package DBTech\Shop\Service\TradePost
 */
class Approver extends \XF\Service\AbstractService
{
	/** @var TradePost */
	protected $tradePost;
	
	
	/**
	 * Approver constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(\XF\App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->tradePost = $tradePost;
	}
	
	/**
	 * @return TradePost
	 */
	public function getTradePost(): TradePost
	{
		return $this->tradePost;
	}
	
	/**
	 * @return bool
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function approve(): bool
	{
		if ($this->tradePost->message_state == 'moderated')
		{
			$this->tradePost->message_state = 'visible';
			$this->tradePost->save();

			$this->onApprove();
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * @throws \Exception
	 */
	protected function onApprove()
	{
		/** @var \DBTech\Shop\Service\TradePost\Notifier $notifier */
		$notifier = $this->service('DBTech\Shop:TradePost\Notifier', $this->tradePost);
		$notifier->notify();
	}
}