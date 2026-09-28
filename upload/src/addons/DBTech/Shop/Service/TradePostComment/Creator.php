<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Entity\TradePostComment;
use XF\Entity\User;

/**
 * Class Creator
 *
 * @package DBTech\Shop\Service\TradePostComment
 */
class Creator extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/** @var TradePost */
	protected $tradePost;

	/** @var TradePostComment */
	protected $comment;

	/** @var User */
	protected $user;

	/** @var \DBTech\Shop\Service\TradePostComment\Preparer */
	protected $preparer;
	
	
	/**
	 * Creator constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(\XF\App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->setTradePost($tradePost);
		$this->setUser(\XF::visitor());
		$this->setDefaults();
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): Creator
	{
		$this->tradePost = $tradePost;
		$this->comment = $tradePost->getNewComment();
		$this->preparer = $this->service('DBTech\Shop:TradePostComment\Preparer', $this->comment);

		return $this;
	}

	/**
	 * @return \DBTech\Shop\Entity\TradePost
	 */
	public function getTradePost(): TradePost
	{
		return $this->tradePost;
	}

	/**
	 * @return \DBTech\Shop\Entity\TradePostComment
	 */
	public function getComment(): TradePostComment
	{
		return $this->comment;
	}

	/**
	 * @return \DBTech\Shop\Service\TradePostComment\Preparer
	 */
	public function getTradePostCommentPreparer(): Preparer
	{
		return $this->preparer;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return $this
	 */
	public function logIp(bool $logIp): Creator
	{
		$this->preparer->logIp($logIp);

		return $this;
	}

	/**
	 * @param \XF\Entity\User $user
	 *
	 * @return $this
	 */
	protected function setUser(User $user): Creator
	{
		$this->user = $user;

		return $this;
	}
	
	/**
	 *
	 */
	protected function setDefaults()
	{
		$this->comment->message_state = $this->tradePost->getNewContentState();
		$this->comment->user_id = $this->user->user_id;
		$this->comment->username = $this->user->username;
	}
	
	/**
	 * @param string $message
	 * @param bool $format
	 *
	 * @return bool
	 */
	public function setContent(string $message, bool $format = true): bool
	{
		return $this->preparer->setMessage($message, $format);
	}
	
	/**
	 *
	 */
	public function checkForSpam()
	{
		if ($this->comment->message_state == 'visible' && $this->user->isSpamCheckRequired())
		{
			$this->preparer->checkForSpam();
		}
	}
	
	/**
	 *
	 */
	protected function finalSetup()
	{
		$this->comment->comment_date = time();
	}
	
	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		$this->comment->preSave();
		return $this->comment->getErrors();
	}
	
	/**
	 * @return TradePostComment
	 * @throws \XF\PrintableException
	 */
	protected function _save(): TradePostComment
	{
		$comment = $this->comment;
		$comment->save();

		$this->preparer->afterInsert();

		return $comment;
	}
	
	/**
	 * @throws \Exception
	 */
	public function sendNotifications()
	{
		if ($this->comment->isVisible())
		{
			/** @var \DBTech\Shop\Service\TradePostComment\Notifier $notifier */
			$notifier = $this->service('DBTech\Shop:TradePostComment\Notifier', $this->comment);
			$notifier->setNotifyMentioned($this->preparer->getMentionedUserIds());
			$notifier->notify();
		}
	}
}