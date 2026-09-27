<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;
use XF\App;
use XF\Repository\IpRepository;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;

class PreparerService extends AbstractService
{
	protected TradePost $tradePost;
	protected bool $logIp = true;
	protected array $mentionedUsers = [];


	/**
	 * @param App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->setTradePost($tradePost);
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): PreparerService
	{
		$this->tradePost = $tradePost;

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
			$user = $this->tradePost->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser();
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
		$this->tradePost->message = $preparer->prepare($message);
		$this->tradePost->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->tradePost);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\Message\PreparerService
	 */
	protected function getMessagePreparer(bool $format = true): \XF\Service\Message\PreparerService
	{
		$preparer = \XF::app()->service(\XF\Service\Message\PreparerService::class, 'dbtech_shop_trade_post', $this->tradePost);
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
		$tradePost = $this->tradePost;

		$user = $tradePost->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser($tradePost->username);
		$message = $tradePost->message;

		$checker = \XF::app()->spam()->contentChecker();
		$checker->check($user, $message, [
			'content_type' => 'dbtech_shop_trade_post',
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
				$tradePost->message_state = 'moderated';
				break;

			case 'denied':
				$checker->logSpamTrigger('dbtech_shop_trade_post', null);
				$tradePost->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
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
		$checker->logSpamTrigger('dbtech_shop_trade_post', $this->tradePost->trade_post_id);
	}

	/**
	 *
	 */
	public function afterUpdate(): void
	{
		$checker = \XF::app()->spam()->contentChecker();
		$checker->logSpamTrigger('dbtech_shop_trade_post', $this->tradePost->trade_post_id);

		// TODO: edit history?
	}

	/**
	 * @param string $ip
	 */
	protected function writeIpLog(string $ip): void
	{
		$tradePost = $this->tradePost;
		if (!$tradePost->user_id)
		{
			return;
		}

		$ipRepo = \XF::app()->repository(IpRepository::class);
		$ipEnt = $ipRepo->logIp($tradePost->user_id, $ip, 'dbtech_shop_trade_post', $tradePost->trade_post_id);
		if ($ipEnt)
		{
			$tradePost->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}