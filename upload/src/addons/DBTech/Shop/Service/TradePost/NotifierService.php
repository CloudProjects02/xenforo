<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;
use XF\App;
use XF\Entity\User;
use XF\Repository\UserAlertRepository;
use XF\Service\AbstractService;

class NotifierService extends AbstractService
{
	protected TradePost $tradePost;
	protected ?array $notifyInsert = null;
	protected array $notifyMentioned = [];
	protected array $usersAlerted = [];


	/**
	 * @param App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(App $app, TradePost $tradePost)
	{
		parent::__construct($app);

		$this->tradePost = $tradePost;
	}

	/**
	 * @return array
	 */
	public function getNotifyInsert(): array
	{
		if ($this->notifyInsert === null)
		{
			$this->notifyInsert = [
				$this->tradePost->Trade->creator_user_id,
				$this->tradePost->Trade->recipient_user_id,
			];
		}
		return $this->notifyInsert;
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
	 * @throws \Exception
	 */
	public function notify(): void
	{
		$notifiableUsers = $this->getUsersForNotification();

		$insertUsers = $this->getNotifyInsert();
		foreach ($insertUsers AS $userId)
		{
			if (isset($notifiableUsers[$userId]))
			{
				$this->sendNotification($notifiableUsers[$userId], 'insert');
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
	}

	/**
	 * @return array|\XF\Entity\User[]
	 * @throws \Exception
	 */
	protected function getUsersForNotification(): array
	{
		$userIds = array_merge(
			$this->getNotifyInsert(),
			$this->getNotifyMentioned()
		);

		$tradePost = $this->tradePost;
		$users = \XF::app()->em()->findByIds(User::class, $userIds, ['Profile', 'Option']);
		if (!$users->count())
		{
			return [];
		}

		$users = $users->toArray();
		foreach ($users AS $id => $user)
		{
			/** @var User $user */
			$canView = \XF::asVisitor($user, function () use ($tradePost): bool { return $tradePost->canView(); });
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
		$tradePost = $this->tradePost;
		if ($user->user_id == $tradePost->user_id)
		{
			return false;
		}

		if (empty($this->usersAlerted[$user->user_id]))
		{
			$alertRepo = \XF::app()->repository(UserAlertRepository::class);
			if ($alertRepo->alert(
				$user,
				$tradePost->user_id,
				$tradePost->username,
				'dbtech_shop_trade_post',
				$tradePost->trade_post_id,
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