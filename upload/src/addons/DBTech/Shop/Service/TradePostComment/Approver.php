<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;

/**
 * Class Approver
 *
 * @package DBTech\Shop\Service\TradePostComment
 */
class Approver extends \XF\Service\AbstractService
{
	/** @var TradePostComment */
	protected $comment;

	/** @var int */
	protected $notifyRunTime = 3;
	
	
	/**
	 * Approver constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePostComment $comment
	 */
	public function __construct(\XF\App $app, TradePostComment $comment)
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
	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}
	
	/**
	 * @return bool
	 * @throws \XF\PrintableException
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
	protected function onApprove()
	{
		if ($this->comment->isLastComment())
		{
			/** @var \DBTech\Shop\Service\TradePostComment\Notifier $notifier */
			$notifier = $this->service('DBTech\Shop:TradePostComment\Notifier', $this->comment);
			$notifier->notify();
		}
	}
}