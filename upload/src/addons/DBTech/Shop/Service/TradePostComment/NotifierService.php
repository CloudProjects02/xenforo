<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;
use DBTech\Shop\Repository\TradePostRepository;
use XF\App;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;

class NotifierService extends AbstractService
{
	protected TradePostComment $comment;
	protected ?array $notifyTradeCreator = null;
	protected ?array $notifyTradePostAuthor = null;
	protected array $notifyMentioned = [];
	protected array $usersAlerted = [];
	protected ?array $notifyOtherCommenters = null;


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
	 * @return array
	 */
	public function getNotifyTradeCreator(): array
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
	public function getNotifyTradePostAuthor(): array
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
	public function setNotifyMentioned(array $mentioned): NotifierService
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
	 * @return array
	 */
	public function getNotifyOtherCommenters(): array
	{
		if ($this->notifyOtherCommenters === null && $this->comment->TradePost)
		{
			$repo = \XF::app()->repository(TradePostRepository::class);
			$comments = $repo->findTradePostComments($this->comment->TradePost, ['visibility' => false])
				->where('message_state', 'visible')
				->fetch();

			$plucked = $comments->pluckNamed('user_id');

			if ($plucked instanceof AbstractCollection)
			{
				$plucked = $plucked->toArray();
			}

			/** @var \DBTech\Shop\Entity\TradePostComment[] $plucked */
			$this->notifyOtherCommenters = $plucked;
		}
		return $this->notifyOtherCommenters;
	}

	/**
	 * @throws \Exception
	 */
	public function notify(): void
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
			$userId = (int) $userId;

			if (isset($notifiableUsers[$userId]))
			{
				$this->sendNotification($notifiableUsers[$userId], 'other_commenter');
			}
		}
	}

	/**
	 * @return array|\XF\Entity\User[]
	 * @throws \Exception
	 */
	protected function getUsersForNotification(): array
	{
		$userIds = array_merge(
			$this->getNotifyTradeCreator(),
			$this->getNotifyTradePostAuthor(),
			$this->getNotifyMentioned(),
			$this->getNotifyOtherCommenters()
		);

		$comment = $this->comment;

		$users = \XF::app()->em()->findByIds(User::class, $userIds, ['Profile', 'Option']);
		if (!$users->count())
		{
			return [];
		}

		$users = $users->toArray();
		foreach ($users AS $id => $user)
		{
			/** @var User $user */
			$canView = \XF::asVisitor($user, function () use ($comment): bool { return $comment->canView(); });
			if (!$canView)
			{
				unset($users[$id]);
			}
		}

		return $users;
	}

	/**
	 * @param User $user
	 * @param string $action
	 *
	 * @return bool
	 */
	protected function sendNotification(User $user, string $action): bool
	{
		$comment = $this->comment;
		if ($user->user_id == $comment->user_id)
		{
			return false;
		}

		if (empty($this->usersAlerted[$user->user_id]))
		{
			$alertRepo = \XF::app()->repository(UserAlertRepository::class);
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