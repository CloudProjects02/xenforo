<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;

/**
 * Class Preparer
 *
 * @package DBTech\Shop\Service\TradePost
 */
class Preparer extends \XF\Service\AbstractService
{
	/** @var TradePost */
	protected $tradePost;

	/** @var bool */
	protected $logIp = true;

	/** @var array */
	protected $mentionedUsers = [];
	
	
	/**
	 * Preparer constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(\XF\App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->setTradePost($tradePost);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): Preparer
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
		if ($limitPermissions && $this->tradePost)
		{
			/** @var \XF\Entity\User $user */
			$user = $this->tradePost->User ?: $this->repository('XF:User')->getGuestUser();
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
		$this->tradePost->message = $preparer->prepare($message);
		$this->tradePost->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->tradePost);
	}

	/**
	 * @param bool $format
	 *
	 * @return \XF\Service\Message\Preparer
	 */
	protected function getMessagePreparer(bool $format = true): \XF\Service\Message\Preparer
	{
		/** @var \XF\Service\Message\Preparer $preparer */
		$preparer = $this->service('XF:Message\Preparer', 'dbtech_shop_trade_post', $this->tradePost);
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
		$tradePost = $this->tradePost;

		/** @var \XF\Entity\User $user */
		$user = $tradePost->User ?: $this->repository('XF:User')->getGuestUser($tradePost->username);
		$message = $tradePost->message;

		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'content_type' => 'dbtech_shop_trade_post'
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
	public function afterInsert()
	{
		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog($ip);
		}

		$checker = $this->app->spam()->contentChecker();
		$checker->logSpamTrigger('dbtech_shop_trade_post', $this->tradePost->trade_post_id);
	}
	
	/**
	 *
	 */
	public function afterUpdate()
	{
		$checker = $this->app->spam()->contentChecker();
		$checker->logSpamTrigger('dbtech_shop_trade_post', $this->tradePost->trade_post_id);

		// TODO: edit history?
	}
	
	/**
	 * @param string $ip
	 */
	protected function writeIpLog(string $ip)
	{
		$tradePost = $this->tradePost;
		if (!$tradePost->user_id)
		{
			return;
		}

		/** @var \XF\Repository\IP $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipEnt = $ipRepo->logIp($tradePost->user_id, $ip, 'dbtech_shop_trade_post', $tradePost->trade_post_id);
		if ($ipEnt)
		{
			$tradePost->fastUpdate('ip_id', $ipEnt->ip_id);
		}
	}
}