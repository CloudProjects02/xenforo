<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;
use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;

class ApproverService extends AbstractService
{
	protected TradePost $tradePost;


	/**
	 * @param App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(App $app, TradePost $tradePost)
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
	 * @throws PrintableException
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
	protected function onApprove(): void
	{
		$notifier = \XF::app()->service(NotifierService::class, $this->tradePost);
		$notifier->notify();
	}
}