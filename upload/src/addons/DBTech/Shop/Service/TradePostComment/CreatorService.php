<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Entity\TradePostComment;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected TradePost $tradePost;
	protected TradePostComment $comment;
	protected User $user;
	protected PreparerService $preparer;


	/**
	 * @param App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->setTradePost($tradePost);
		$this->setUser(\XF::visitor());
		$this->setDefaults();
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): CreatorService
	{
		$this->tradePost = $tradePost;
		$this->comment = $tradePost->getNewComment();
		$this->preparer = \XF::app()->service(PreparerService::class, $this->comment);

		return $this;
	}

	/**
	 * @return TradePost
	 */
	public function getTradePost(): TradePost
	{
		return $this->tradePost;
	}

	/**
	 * @return TradePostComment
	 */
	public function getComment(): TradePostComment
	{
		return $this->comment;
	}

	/**
	 * @return PreparerService
	 */
	public function getTradePostCommentPreparer(): PreparerService
	{
		return $this->preparer;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return $this
	 */
	public function logIp(bool $logIp): CreatorService
	{
		$this->preparer->logIp($logIp);

		return $this;
	}

	/**
	 * @param User $user
	 *
	 * @return $this
	 */
	protected function setUser(User $user): CreatorService
	{
		$this->user = $user;

		return $this;
	}

	/**
	 *
	 */
	protected function setDefaults(): void
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
	public function checkForSpam(): void
	{
		if ($this->comment->message_state == 'visible' && $this->user->isSpamCheckRequired())
		{
			$this->preparer->checkForSpam();
		}
	}

	/**
	 *
	 */
	protected function finalSetup(): void
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
	 * @throws PrintableException
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
	public function sendNotifications(): void
	{
		if ($this->comment->isVisible())
		{
			$notifier = \XF::app()->service(NotifierService::class, $this->comment);
			$notifier->setNotifyMentioned($this->preparer->getMentionedUserIds());
			$notifier->notify();
		}
	}
}