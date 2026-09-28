<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Service\User;

use DBTech\Security\Entity\AdminStrike;
use DBTech\Security\Entity\LoginStrike;
use DBTech\Security\Repository\WatcherRepository;
use DBTech\Security\XF\Entity\User;
use XF\PrintableException;

/**
 * @extends \XF\Service\User\LoginService
 */
class LoginService extends XFCP_LoginService
{
	/**
	 * @param $limitType
	 *
	 * @return bool
	 */
	public function isLoginLimited(&$limitType = null)
	{
		$retval = parent::isLoginLimited($limitType);
		if (!$retval
			&& \XF::app()->get('app.classType') == 'Pub'
			&& \XF::options()->dbtechSecurityLoginCaptcha['public']
			&& \XF::app()->request()->getRoutePath() == 'login/login'
		)
		{
			// If this returned true, we already have a login limit which should override this
			$limitType = 'captcha';
			return true;
		}

		return $retval;
	}

	/**
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function recordFailedAttempt()
	{
		parent::recordFailedAttempt();

		if (!$this->ip || !$this->recordAttempts)
		{
			return;
		}

		/** @var User $user */
		$user = $this->getUser();

		/** @var User $visitor */
		$visitor = \XF::visitor();

		if (\XF::app()->get('app.classType') == 'Admin')
		{
			$adminStrike = \XF::app()->em()->create(AdminStrike::class);
			$adminStrike->username = \mb_substr($this->login, 0, 50);
			$adminStrike->ipaddress = $this->ip;
			$adminStrike->user_id = $visitor->user_id;
			$adminStrike->save();
		}

		$loginStrike = \XF::app()->em()->create(LoginStrike::class);
		$loginStrike->username = \mb_substr($this->login, 0, 50);
		$loginStrike->ipaddress = $this->ip;
		$loginStrike->valid_user = (bool) $user;
		$loginStrike->save();

		$watcherRepo = \XF::app()->repository(WatcherRepository::class);

		if ($user)
		{
			if (\XF::app()->options()->dbtechSecurityBreachCheck['enabled'])
			{
				$watcherRepo->breachCheck($user);
			}

			if ($user->is_staff)
			{
				if ($user->dbtech_security_lastbreach)
				{
					$failedBreachedStaffLogonHandler = $watcherRepo->getHandler('failedbreachedstafflogon', false);
					if ($failedBreachedStaffLogonHandler)
					{
						$failedBreachedStaffLogonHandler->trigger([
							'username' => $this->login,
							'ipaddress' => $this->ip,
						], $user);
					}
				}

				$failedStaffLogonHandler = $watcherRepo->getHandler('failedstafflogon', false);
				if ($failedStaffLogonHandler)
				{
					$failedStaffLogonHandler->trigger([
						'username' => $this->login,
						'ipaddress' => $this->ip,
					], $user);
				}
			}
			else
			{
				if ($user->dbtech_security_lastbreach)
				{
					$failedBreachedLogonHandler = $watcherRepo->getHandler('failedbreachedlogon', false);
					if ($failedBreachedLogonHandler)
					{
						$failedBreachedLogonHandler->trigger([
							'username' => $this->login,
							'ipaddress' => $this->ip,
						], $user);
					}
				}

				$failedLogonHandler = $watcherRepo->getHandler('failedlogon', false);
				if ($failedLogonHandler)
				{
					$failedLogonHandler->trigger([
						'username' => $this->login,
						'ipaddress' => $this->ip,
					], $user);
				}
			}

			$failedMassLogonHandler = $watcherRepo->getHandler('failedmasslogon', false);
			if ($failedMassLogonHandler)
			{
				$failedMassLogonHandler->trigger([
					'ipaddress' => $this->ip,
				], $user);
			}
		}
		else
		{
			$failedLogonBogusHandler = $watcherRepo->getHandler('failedlogonbogus', false);
			if ($failedLogonBogusHandler)
			{
				$failedLogonBogusHandler->trigger([
					'username' => $this->login,
					'ipaddress' => $this->ip,
				]);
			}

			$failedMassLogonBogusHandler = $watcherRepo->getHandler('failedmasslogonbogus', false);
			if ($failedMassLogonBogusHandler)
			{
				$failedMassLogonBogusHandler->trigger([
					'ipaddress' => $this->ip,
				]);
			}
		}

		if (\XF::app()->get('app.classType') == 'Admin')
		{
			$failedLogonAdminHandler = $watcherRepo->getHandler('failedlogonadmin', false);
			if ($failedLogonAdminHandler)
			{
				$failedLogonAdminHandler->trigger([
					'username' => $this->login,
					'ipaddress' => $this->ip,
				]);
			}
		}
	}
}