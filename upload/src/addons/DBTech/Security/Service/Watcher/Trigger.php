<?php

namespace DBTech\Security\Service\Watcher;

use DBTech\Security\Entity\AccountLock;
use DBTech\Security\Entity\Watcher;
use DBTech\Security\Repository\WatcherRepository;
use DBTech\Security\XF\Logger;
use XF\App;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\PrintableException;
use XF\Repository\BanningRepository;
use XF\Repository\OptionRepository;
use XF\Service\AbstractService;
use XF\Util\Ip;
use XF\Util\Php;

class Trigger extends AbstractService
{
	protected ?Watcher $watcher;
	protected ?User $user;
	protected ?string $logMessage = null;
	protected ?array $params = null;
	protected bool $log = true;


	/**
	 * @param App $app
	 * @param Watcher|null $watcher
	 * @param User|null $user
	 */
	public function __construct(App $app, ?Watcher $watcher = null, ?User $user = null)
	{
		parent::__construct($app);

		$this->watcher = $watcher;
		$this->user = $user;

		$this->setupDefaults();
	}

	/**
	 *
	 */
	protected function setupDefaults()
	{
		//		$this->user = $this->user ?: \XF::visitor();
	}

	/**
	 * @param User $user
	 *
	 * @return $this
	 */
	public function setUser(User $user): Trigger
	{
		$this->user = $user;

		return $this;
	}

	/**
	 * @return User|null
	 */
	public function getUser(): ?User
	{
		return $this->user;
	}

	/**
	 * @param string $logMessage
	 *
	 * @return $this
	 */
	public function setLogMessage(string $logMessage): Trigger
	{
		$this->logMessage = $logMessage;

		return $this;
	}

	/**
	 * @param array $params
	 *
	 * @return $this
	 */
	public function setParams(array $params): Trigger
	{
		$this->params = $params;

		return $this;
	}

	/**
	 * @return array|null
	 */
	public function getParams(): ?array
	{
		return $this->params;
	}

	/**
	 * @param bool $log
	 *
	 * @return $this
	 */
	public function setLog(bool $log): Trigger
	{
		$this->log = $log;

		return $this;
	}

	/**
	 * @return bool
	 * @throws PrintableException
	 */
	public function trigger(): bool
	{
		if ($this->checkAllowedIp(\XF::app()->request()->getIp()))
		{
			// Current IP should never be actioned against
			return false;
		}

		$watcher = $this->watcher;

		if ($watcher->actions['closeForum'])
		{
			$this->actionCloseForum();
		}

		if ($watcher->actions['emailWebmaster'])
		{
			$this->actionEmailWebmaster();
		}

		if ($watcher->actions['banIp'])
		{
			$this->actionBanIp();
		}

		//		$this->setUser(\XF::app()->em()->find(\XF\Entity\User::class, 2));

		if ($this->user)
		{
			if (!empty($watcher->actions['banUser']))
			{
				$this->actionBanUser();
			}

			if (!empty($watcher->actions['emailUser']))
			{
				$this->actionEmailUser();
			}

			if (!empty($watcher->actions['lockChange']))
			{
				$this->actionLockAccount('change');
			}

			if (!empty($watcher->actions['lockReset']))
			{
				$this->actionLockAccount('reset');
			}

			if (!empty($watcher->actions['lockUser']))
			{
				$this->actionLockAccount('user');
			}

			if (!empty($watcher->actions['adminLockUser']))
			{
				$this->actionLockAccount('admin');
			}
		}

		return true;
	}

	/**
	 * @param string $action
	 * @param array $params
	 * @param User|null $actor
	 *
	 * @throws PrintableException
	 */
	protected function logAction(string $action, array $params = [], ?User $actor = null): void
	{
		if (!$this->log)
		{
			return;
		}

		/** @var Logger $logger */
		$logger = \XF::app()->logger();
		$logger->logDbtechSecurityWatcher(
			$this->watcher,
			$action,
			$this->logMessage,
			$params,
			true,
			$actor
		);
	}

	/**
	 * @param string $ipToCheck
	 *
	 * @return bool
	 */
	protected function checkAllowedIp(string $ipToCheck): bool
	{
		$binary = Ip::stringToBinary($ipToCheck);

		foreach (\XF::options()->dbtech_security_globalallowip AS $ip)
		{
			if ($ip['isRange'])
			{
				$parsed = Ip::parseIpRangeString($ip['ip']);

				if (!$parsed)
				{
					continue;
				}

				if (Ip::ipMatchesRange($binary, $parsed['startRange'], $parsed['endRange']))
				{
					return true;
				}
			}
			else if ($ip['ip'] == $ipToCheck)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @throws PrintableException
	 */
	protected function actionCloseForum(): void
	{
		$params = $this->getParams();

		$optionRepo = \XF::app()->repository(OptionRepository::class);
		$optionRepo->updateOptions([
			'boardActive' => 0,
			'boardInactiveMessage' => \XF::options()->dbtech_security_breach_closedreason,
		]);

		$this->logAction('close_forum', $params);
	}

	/**
	 * @throws PrintableException
	 */
	protected function actionEmailWebmaster(): void
	{
		$params = $this->getParams();
		$watcher = $this->watcher;

		// Create appropriate mail object
		\XF::app()->mailer()->newMail()
			->setTemplate('dbtech_security_alert', [
				'watcher' => $watcher,
				'differences' => $this->logMessage,
			])
			->setTo(\XF::options()->contactEmailAddress)
			->queue()
		;

		$this->logAction('email_webmaster', $params);
	}

	/**
	 * @throws PrintableException
	 */
	protected function actionBanUser(): void
	{
		$params = $this->getParams();
		$user = $this->user;

		if (!$user)
		{
			return;
		}

		$params['ip_address'] = \XF::app()->request()->getIp();

		$banningRepo = \XF::app()->repository(BanningRepository::class);
		$banningRepo->banUser($user, 0, \XF::options()->dbtech_security_breach_bannedreason, $errorKey);

		$this->logAction('ban_user', $params, $user);
	}

	/**
	 * @throws PrintableException
	 */
	protected function actionBanIp(): void
	{
		$params = $this->getParams();
		$ipToBan = \XF::app()->request()->getIp();

		//		$ipToBan = '2.2.2.2';

		$params['ip_address'] = $ipToBan;

		$banningRepo = \XF::app()->repository(BanningRepository::class);
		$ipBans = $banningRepo->findIpBans()->where('ip', $ipToBan)->total();
		if (!$ipBans)
		{
			$banningRepo->banIp($ipToBan, \XF::options()->dbtech_security_breach_bannedreason);

			$this->logAction('ban_ip', $params);
		}
	}

	/**
	 * @throws PrintableException
	 */
	public function actionEmailUser(): void
	{
		$params = $this->getParams();
		$watcher = $this->watcher;
		$user = $this->user;

		if (!$user)
		{
			return;
		}

		if (!$user->email)
		{
			// User had no email
			return;
		}

		// Create appropriate mail object
		\XF::app()->mailer()->newMail()
			->setTemplate('dbtech_security_alert_user', [
				'watcher' => $watcher,
				'noActions' => true,
			])
			->setToUser($user)
			->queue()
		;

		$this->logAction('email_user', $params);
	}

	/**
	 * @param string $type
	 */
	public function actionLockAccount(string $type): void
	{
		if (!$this->user)
		{
			return;
		}

		// Validate type
		$type = $this->isValidLockType($type) ? $type : 'change';

		$method = '_lock' . Php::camelCase($type);
		if (method_exists($this, $method))
		{
			$this->$method();
		}
	}

	/**
	 * @throws PrintableException
	 */
	protected function _lockChange(): void
	{
		$params = $this->getParams();
		$user = $this->user;

		if (!$user)
		{
			return;
		}

		$user->setOption('prevent_self_lock', false);
		$user->security_lock = 'change';
		$user->save();

		if ($this->watcher)
		{
			$params['type'] = 'admin';

			$this->logAction('forced_password_change', $params);
		}
	}

	/**
	 * @throws PrintableException
	 */
	protected function _lockReset(): void
	{
		$params = $this->getParams();
		$user = $this->user;

		if (!$user)
		{
			return;
		}

		$user->setOption('prevent_self_lock', false);
		$user->security_lock = 'reset';
		$user->save();

		if ($this->watcher)
		{
			$params['type'] = 'admin';

			$this->logAction('forced_password_reset', $params);
		}
	}

	/**
	 * @throws PrintableException
	 */
	protected function _lockAdmin(): void
	{
		$params = $this->getParams();
		$user = $this->user;

		if (!$user)
		{
			return;
		}

		// The unlock hash
		$hash = md5(\XF::generateRandomString(32));

		// Shorthand
		$mailer = \XF::app()->mailer();

		$admins = \XF::app()->finder(UserFinder::class)
			->where('user_id', '!=', $user->user_id)
			->where('is_admin', 1)
			->fetch()
		;

		if ($admins->count())
		{
			$user->Option->dbtech_security_is_user_locked = 0;
			$user->Option->dbtech_security_is_admin_locked = 1;
			$user->Option->saveIfChanged();

			if ($user->DBTechSecurityAccountLock)
			{
				$user->DBTechSecurityAccountLock->fastUpdate([
					'action'  	=> 'admin',
					'hash' 		=> $hash,
				]);
			}
			else
			{
				$lock = \XF::app()->em()->create(AccountLock::class);
				$lock->bulkSet([
					'user_id' 	=> $user->user_id,
					'is_staff' 	=> $user->is_staff,
					'action'  	=> 'admin',
					'hash' 		=> $hash,
				]);
				$lock->save();
			}
		}
		else if ($user->DBTechSecurityAccountLock)
		{
			\XF::app()->repository(WatcherRepository::class)
				->unlockAccount($user)
			;
		}

		foreach ($admins AS $admin)
		{
			if (!$admin->email)
			{
				// Admin had no email
				continue;
			}

			// Send the admin unlock request
			$mailer->newMail()
				->setTemplate('dbtech_security_account_lock_admin', [
					'hash' => $hash,
					'lockedUser' => $user,
				])
				->setToUser($admin)
				->queue()
			;
		}

		if ($user->email)
		{
			// Create appropriate mail object
			$mailer->newMail()
				->setTemplate('dbtech_security_account_lock')
				->setToUser($user)
				->queue()
			;
		}

		if ($this->watcher)
		{
			$params['type'] = 'admin';
			$params['hash'] = $hash;

			$this->logAction('admin_lock_user', $params);
		}
	}

	/**
	 * @throws PrintableException
	 */
	protected function _lockUser(): void
	{
		$params = $this->getParams();
		$user = $this->user;

		if (!$user)
		{
			return;
		}

		// The unlock hash
		$hash = md5(\XF::generateRandomString(32));

		// Shorthand
		$mailer = \XF::app()->mailer();

		$user->Option->dbtech_security_is_user_locked = 1;
		$user->Option->dbtech_security_is_admin_locked = 0;
		$user->Option->saveIfChanged();

		if ($user->DBTechSecurityAccountLock)
		{
			$user->DBTechSecurityAccountLock->fastUpdate([
				'action'  	=> 'user',
				'hash' 		=> $hash,
			]);
		}
		else
		{
			$lock = \XF::app()->em()->create(AccountLock::class);
			$lock->bulkSet([
				'user_id' 	=> $user->user_id,
				'is_staff' 	=> $user->is_staff,
				'action'  	=> 'user',
				'hash' 		=> $hash,
			]);
			$lock->save();
		}

		if ($user->email)
		{
			// Create appropriate mail object
			$mailer->newMail()
				->setTemplate('dbtech_security_account_lock', [
					'toUser' => $user,
					'hash' => $hash,
				])
				->setToUser($user)
				->queue();
		}

		if ($this->watcher)
		{
			$params['type'] = 'user';
			$params['hash'] = $hash;

			$this->logAction('lock_user', $params);
		}
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	protected function isValidLockType(string $type): bool
	{
		return in_array($type, [
			'change',
			'reset',
			'user',
			'admin',
		]);
	}
}