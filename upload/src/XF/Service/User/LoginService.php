<?php

namespace XF\Service\User;

use XF\App;
use XF\Behavior\ChangeLoggable;
use XF\Entity\User;
use XF\Entity\UserAuth;
use XF\Service\AbstractService;
use XF\Service\LoginLimitTrait;
use XF\Validator\Email;

use function strlen;

class LoginService extends AbstractService
{
	use LoginLimitTrait;

	protected $login;
	protected $ip;

	protected $recordAttempts = true;
	protected $allowPasswordUpgrade = true;

	public function __construct(App $app, $login, $ip)
	{
		parent::__construct($app);

		$this->login = $login;
		$this->ip = $ip;
	}

	public function setRecordAttempts($value)
	{
		$this->recordAttempts = (bool) $value;
	}

	public function getRecordAttempts()
	{
		return $this->recordAttempts;
	}

	public function setAllowPasswordUpgrade($value)
	{
		$this->allowPasswordUpgrade = (bool) $value;
	}

	public function getAllowPasswordUpgrade()
	{
		return $this->allowPasswordUpgrade;
	}

	public function isLoginLimited(&$limitType = null)
	{
		if (!strlen($this->login) || !$this->ip)
		{
			return false;
		}

		if ($this->hasTooManyLoginAttempts($this->ip))
		{
			$limitType = $this->app->options()->loginLimit;
			return true;
		}

		return false;
	}

	public function hasTooManyLoginAttempts($ip)
	{
		return $this->checkTooManyLoginAttempts($ip, $this->login);
	}

	public function getAttemptLimits()
	{
		return $this->getLoginAttemptLimits();
	}

	public function validate($password, &$error = null)
	{
		if (!strlen($this->login))
		{
			$error = \XF::phrase('requested_user_not_found');
			return null;
		}

		$user = $this->getUser();
		if (!$user)
		{
			$this->recordFailedAttempt();

			$error = \XF::phrase('requested_user_x_not_found', ['name' => $this->login]);
			return null;
		}

		if (!strlen($password))
		{
			// don't log an attempt if they don't provide a password

			$error = \XF::phrase('incorrect_password');
			return null;
		}

		$auth = $user->Auth;
		if (!$auth || !$auth->authenticate($password))
		{
			$this->recordFailedAttempt();

			$error = \XF::phrase('incorrect_password');
			return null;
		}

		if ($this->allowPasswordUpgrade)
		{
			/** @var UserAuth $userAuth */
			$userAuth = $user->Auth;
			if ($userAuth->getAuthenticationHandler()->isUpgradable())
			{
				$userAuth->getBehavior(ChangeLoggable::class)->setOption('enabled', false);
				$userAuth->setPassword($password, null, false); // don't update the password date as this isn't a real change
				$userAuth->save();
			}
		}

		$this->clearFailedAttempts();

		return $user;
	}

	/**
	 * @return null|User
	 */
	protected function getUser()
	{
		$emailValidator = $this->app->validator(Email::class);
		$email = $emailValidator->coerceValue($this->login);
		if ($emailValidator->isValid($email))
		{
			$user = $this->findOne(User::class, ['email' => $email], ['Auth']);
			if ($user)
			{
				return $user;
			}
		}

		return $this->findOne(User::class, ['username' => $this->login], ['Auth']);
	}

	protected function recordFailedAttempt()
	{
		$this->recordLoginAttempt($this->login, $this->ip);
	}

	protected function clearFailedAttempts()
	{
		$this->clearLoginAttempts($this->login, $this->ip);
	}
}
