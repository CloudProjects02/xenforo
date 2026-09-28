<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;

/**
 * Class Preparer
 *
 * @package DBTech\Shop\Service\TradePostComment
 */
class Preparer extends \XF\Service\AbstractService
{
	/** @var TradePostComment */
	protected $comment;

	/** @var bool */
	protected $logIp = true;

	/** @var array */
	protected $mentionedUsers = [];
	
	
	/**
	 * Preparer constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePostComment $comment
	 */
	public function __construct(\XF\App $app, TradePostComment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 *
	 * @return $this
	 */
	protected function setComment(TradePostComment $comment): Preparer
	{
		$this->comment = $comment;

		return $this;
	}
	
	/**
	 * @return TradePostComment
	 */
	public function getComment(): TradePostComment
	{
		return $this->comment;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return $this
	 */
	public function logIp(bool $logIp): Preparer
	{
		$this->logIp = $logIp;

		return $this;
	}
	
	/**
	 * @param bool $limitPermissions
	 *
	 * @return array
	 */
	public function getMentionedUsers(bool $limitPermissions = true): array
	{
		if ($limitPermissions && $this->comment)
		{
			/** @var \XF\Entity\User $user */
			$user = $this->comment->User ?: $this->repository('XF:User')->getGuestUser();
			return $user->getAllowedUserMentions($this->mentionedUsers);
		}
		else
		{
			return $this->mentionedUsers;
		}
	}
	
	/**
	 * @param bool $limitPermissions
	 *
	 * @return array
	 */
	public function getMentionedUserIds(bool $limitPermissions = true): array
	{
		return array_keys($this->getMentionedUsers($limitPermissions));
	}
	
	/**
	 * @param string $message
	 * @param bool $format
	 *
	 * @return bool
	 */
	public function setMessage(string $message, bool $format = true): bool
	{
		$preparer = $this->getMessagePreparer($format);
		$preparer->setConstraint('maxLength', $this->app->options()->dbtechShopTradePostMaxLength);
		$this->comment->message = $preparer->prepare($message);
		$this->comment->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->comment);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\Message\Preparer
	 */
	protected function getMessagePreparer(bool $format = true): \XF\Service\Message\Preparer
	{
		/** @var \XF\Service\Message\Preparer $preparer */
		$preparer = $this->service('XF:Message\Preparer', 'dbtech_shop_trade_comment', $this->comment);
		$preparer->enableFilter('structuredText');
		if (!$format)
		{
			$preparer->disableAllFilters();
		}

		return $preparer;
	}
	
	/**
	 *
	 */
	public function checkForSpam()
	{
		$comment = $this->comment;

		/** @var \XF\Entity\User $user */
		$user = $comment->User ?: $this->repository('XF:User')->getGuestUser($comment->username);
		$message = $comment->message;

		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'content_type' => 'dbtech_shop_trade_comment'
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
				$comment->message_state = 'moderated';
				break;

			case 'denied':
				$checker->logSpamTrigger('dbtech_shop_trade_comment', null);
				$comment->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
				break;
		}
	}
	
	/**
	 *
	 */
	public function afterInsert()
	{
		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}

		$checker = $this->app->spam()->contentChecker();
		$checker->logSpamTrigger('dbtech_shop_trade_comment', $this->comment->trade_post_comment_id);
	}
	
	/**
	 *
	 */
	public function afterUpdate()
	{
		$checker = $this->app->spam()->contentChecker();
		$checker->logSpamTrigger('dbtech_shop_trade_comment', $this->comment->trade_post_comment_id);

		// TODO: edit history?
	}
	
	/**
	 * @param string $ip
	 */
	protected function writeIpLog(string $ip)
	{
		$comment = $this->comment;
		if (!$comment->user_id)
		{
			return;
		}

		/** @var \XF\Repository\IP $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipEnt = $ipRepo->logIp($comment->user_id, $ip, 'dbtech_shop_trade_comment', $comment->trade_post_comment_id);
		if ($ipEnt)
		{
			$comment->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}