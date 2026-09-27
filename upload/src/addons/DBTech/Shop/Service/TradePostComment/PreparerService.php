<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;
use XF\App;
use XF\Repository\IpRepository;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;

class PreparerService extends AbstractService
{
	protected TradePostComment $comment;
	protected bool $logIp = true;
	protected array $mentionedUsers = [];


	/**
	 * @param App $app
	 * @param TradePostComment $comment
	 */
	public function __construct(App $app, TradePostComment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	/**
	 * @param TradePostComment $comment
	 *
	 * @return $this
	 */
	protected function setComment(TradePostComment $comment): PreparerService
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
	public function logIp(bool $logIp): PreparerService
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
		if ($limitPermissions)
		{
			$user = $this->comment->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser();
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
		$preparer->setConstraint('maxLength', \XF::app()->options()->dbtechShopTradePostMaxLength);
		$this->comment->message = $preparer->prepare($message);
		$this->comment->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->comment);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\Message\PreparerService
	 */
	protected function getMessagePreparer(bool $format = true): \XF\Service\Message\PreparerService
	{
		$preparer = \XF::app()->service(\XF\Service\Message\PreparerService::class, 'dbtech_shop_trade_comment', $this->comment);
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
	public function checkForSpam(): void
	{
		$comment = $this->comment;

		$user = $comment->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser($comment->username);
		$message = $comment->message;

		$checker = \XF::app()->spam()->contentChecker();
		$checker->check($user, $message, [
			'content_type' => 'dbtech_shop_trade_comment',
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
	public function afterInsert(): void
	{
		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? \XF::app()->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}

		$checker = \XF::app()->spam()->contentChecker();
		$checker->logSpamTrigger('dbtech_shop_trade_comment', $this->comment->trade_post_comment_id);
	}

	/**
	 *
	 */
	public function afterUpdate(): void
	{
		$checker = \XF::app()->spam()->contentChecker();
		$checker->logSpamTrigger('dbtech_shop_trade_comment', $this->comment->trade_post_comment_id);

		// TODO: edit history?
	}

	/**
	 * @param string $ip
	 */
	protected function writeIpLog(string $ip): void
	{
		$comment = $this->comment;
		if (!$comment->user_id)
		{
			return;
		}

		$ipRepo = \XF::app()->repository(IpRepository::class);
		$ipEnt = $ipRepo->logIp($comment->user_id, $ip, 'dbtech_shop_trade_comment', $comment->trade_post_comment_id);
		if ($ipEnt)
		{
			$comment->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}