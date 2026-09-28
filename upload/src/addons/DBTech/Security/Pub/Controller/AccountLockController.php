<?php

namespace DBTech\Security\Pub\Controller;

use DBTech\Security\Pub\View;
use DBTech\Security\Repository\WatcherRepository;
use DBTech\Security\Service\Watcher\Trigger;
use XF\Entity\User;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class AccountLockController extends AbstractController
{
	/**
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionIndex(): AbstractReply
	{
		$this->assertRegistrationRequired();
		$this->assertIsLocked();

		return $this->view(
			View\AccountLock\IndexView::class,
			'dbtech_security_locked_account'
		);
	}

	/**
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionResend(): AbstractReply
	{
		$this->assertRegistrationRequired();
		$this->assertIsUserLocked();

		$watcherService = \XF::app()->service(Trigger::class, null, \XF::visitor());
		$watcherService->actionLockAccount('user');

		return $this->message(\XF::phrase('dbtech_security_unlock_code_sent'));
	}

	/**
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionUnlock(): AbstractReply
	{
		$userId = $this->filter('user_id', 'uint');
		$hash = $this->filter('hash', 'str');

		$lockedUser = $this->assertValidatedUnlockHash($userId, $hash);

		\XF::app()->repository(WatcherRepository::class)
			->unlockAccount($lockedUser)
		;

		return $this->message(\XF::phrase('dbtech_security_account_unlocked'));
	}

	/**
	 * @throws Exception
	 */
	protected function assertIsLocked(): void
	{
		/** @var \DBTech\Security\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->Option->dbtech_security_is_user_locked
			&& !$visitor->Option->dbtech_security_is_admin_locked
		)
		{
			throw $this->exception($this->message(\XF::phrase('dbtech_security_not_locked'), 403));
		}
	}

	/**
	 * @throws Exception
	 */
	protected function assertIsUserLocked(): void
	{
		$this->assertIsLocked();

		/** @var \DBTech\Security\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->Option->dbtech_security_is_admin_locked)
		{
			throw $this->exception($this->message(\XF::phrase('dbtech_security_account_can_only_be_admin_unlocked'), 403));
		}
	}

	/**
	 * @param int|null $userId
	 * @param string $confirmKey
	 *
	 * @return User
	 * @throws Exception
	 */
	protected function assertValidatedUnlockHash(?int $userId, string $confirmKey): User
	{
		$user = \XF::app()->em()->find(User::class, $userId);
		if (!$user)
		{
			throw $this->exception(
				$this->error(\XF::phrase('this_link_is_not_usable_by_you'), 403)
			);
		}

		if (!$user->DBTechSecurityAccountLock || $confirmKey !== $user->DBTechSecurityAccountLock->hash)
		{
			throw $this->exception(
				$this->error(\XF::phrase('this_link_could_not_be_verified'), 403)
			);
		}

		if ($user->DBTechSecurityAccountLock->action == 'admin'
			&& !\XF::visitor()->is_admin
		)
		{
			throw $this->exception(
				$this->error(\XF::phrase('this_link_is_not_usable_by_you'), 403)
			);
		}

		return $user;
	}
}