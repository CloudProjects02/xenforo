<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;
use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;

class ApproverService extends AbstractService
{
	protected TradePostComment $comment;
	protected int $notifyRunTime = 3;


	/**
	 * @param App $app
	 * @param TradePostComment $comment
	 */
	public function __construct(App $app, TradePostComment $comment)
	{
		parent::__construct($app);
		$this->comment = $comment;
	}

	/**
	 * @return TradePostComment
	 */
	public function getComment(): TradePostComment
	{
		return $this->comment;
	}

	/**
	 * @param $time
	 */
	public function setNotifyRunTime($time): void
	{
		$this->notifyRunTime = $time;
	}

	/**
	 * @return bool
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function approve(): bool
	{
		if ($this->comment->message_state == 'moderated')
		{
			$this->comment->message_state = 'visible';
			$this->comment->save();

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
		if ($this->comment->isLastComment())
		{
			$notifier = \XF::app()->service(NotifierService::class, $this->comment);
			$notifier->notify();
		}
	}
}