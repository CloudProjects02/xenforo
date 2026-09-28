<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;
use XF\Service\AbstractService;

/**
 * Class Notifier
 *
 * @package DBTech\Shop\Service\TradePostComment
 */
class Notifier extends AbstractService
{
	/** @var TradePostComment */
	protected $comment;

	/** @var array */
	protected $notifyTradeCreator;

	/** @var array */
	protected $notifyTradePostAuthor;

	/** @var array */
	protected $notifyMentioned = [];

	/** @var \XF\Mvc\Entity\ArrayCollection */
	protected $notifyOtherCommenters;

	/** @var array */
	protected $usersAlerted = [];
	
	
	/**
	 * Notifier constructor.
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
	 * @return array
	 */
	public function getNotifyTradeCreator(): ?array
	{
		if ($this->notifyTradeCreator === null)
		{
			$this->notifyTradeCreator = [$this->comment->TradePost->Trade->creator_user_id];
		}
		return $this->notifyTradeCreator;
	}
	
	/**
	 * @return array
	 */
	public function getNotifyTradePostAuthor(): ?array
	{
		if ($this->notifyTradePostAuthor === null)
		{
			$this->notifyTradePostAuthor = [$this->comment->TradePost->user_id];
		}
		return $this->notifyTradePostAuthor;
	}

	/**
	 * @param array $mentioned
	 *
	 * @return $this
	 */
	public function setNotifyMentioned(array $mentioned): Notifier
	{
		$this->notifyMentioned = array_unique($mentioned);

		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getNotifyMentioned(): array
	{
		return $this->notifyMentioned;
	}
	
	/**
	 * @return array|\XF\Mvc\Entity\ArrayCollection|null
	 */
	public function getNotifyOtherCommenters()
	{
		if ($this->notifyOtherCommenters === null && $this->comment->TradePost)
		{
			/** @var \DBTech\Shop\Repository\TradePost $repo */
			$repo = $this->repository('DBTech\Shop:TradePost');
			$comments = $repo->findTradePostComments($this->comment->TradePost, ['visibility' => false])
				->where('message_state', 'visible')
				->fetch();

			$this->notifyOtherCommenters = $comments->pluckNamed('user_id');
		}
		return $this->notifyOtherCommenters;
	}
	
	/**
	 * @throws \Exception
	 */
	public function notify()
	{
		$notifiableUsers = $this->getUsersForNotification();
		
		$tradeUserIds = $this->getNotifyTradeCreator();
		foreach ($tradeUserIds AS $userId)
		{
			if (isset($notifiableUsers[$userId]))
			{
				$this->sendNotification($notifiableUsers[$userId], 'your_trade');
			}
		}

		$tradePostAuthors = $this->getNotifyTradePostAuthor();
		foreach ($tradePostAuthors AS $userId)
		{
			if (isset($notifiableUsers[$userId]))
			{
				$this->sendNotification($notifiableUsers[$userId], 'your_post');
			}
		}

		$mentionUsers = $this->getNotifyMentioned();
		foreach ($mentionUsers AS $userId)
		{
			if (isset($notifiableUsers[$userId]))
			{
				$this->sendNotification($notifiableUsers[$userId], 'mention');
			}
		}

		$otherCommenters = $this->getNotifyOtherCommenters();
		foreach ($otherCommenters AS $userId)
		{
			if (isset($notifiableUsers[$userId]))
			{
				$this->sendNotification($notifiableUsers[$userId], 'other_commenter');
			}
		}
	}
	
	/**
	 * @return array|\XF\Mvc\Entity\ArrayCollection|\XF\Mvc\Entity\Entity[]
	 * @throws \Exception
	 */
	protected function getUsersForNotification()
	{
		$userIds = array_merge(
			$this->getNotifyTradeCreator(),
			$this->getNotifyTradePostAuthor(),
			$this->getNotifyMentioned(),
			$this->getNotifyOtherCommenters()
		);

		$comment = $this->comment;

		$users = $this->app->em()->findByIds('XF:User', $userIds, ['Profile', 'Option']);
		if (!$users->count())
		{
			return [];
		}

		$users = $users->toArray();
		foreach ($users AS $id => $user)
		{
			/** @var \XF\Entity\User $user */
			$canView = \XF::asVisitor($user, function () use ($comment): bool { return $comment->canView(); });
			if (!$canView)
			{
				unset($users[$id]);
			}
		}

		return $users;
	}
	
	/**
	 * @param \XF\Entity\User $user
	 * @param string $action
	 *
	 * @return bool
	 */
	protected function sendNotification(\XF\Entity\User $user, string $action): bool
	{
		$comment = $this->comment;
		if ($user->user_id == $comment->user_id)
		{
			return false;
		}

		if (empty($this->usersAlerted[$user->user_id]))
		{
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->app->repository('XF:UserAlert');
			if ($alertRepo->alert(
				$user,
				$comment->user_id,
				$comment->username,
				'dbtech_shop_trade_comment',
				$comment->trade_post_comment_id,
				$action
			))
			{
				$this->usersAlerted[$user->user_id] = true;
				return true;
			}
		}

		return false;
	}
}