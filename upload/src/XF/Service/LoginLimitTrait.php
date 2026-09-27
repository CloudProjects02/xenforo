<?php

namespace XF\Service;

use XF\Repository\LoginAttemptRepository;

trait LoginLimitTrait
{
	protected function checkTooManyLoginAttempts($ip, $login = null)
	{
		if (!$ip)
		{
			return false;
		}

		$limits = $this->getLoginAttemptLimits();
		$attemptRepo = $this->repository(LoginAttemptRepository::class);

		foreach ($limits AS $limit)
		{
			$loginParam = ($limit['type'] == 'user' && $login ? $login : null);
			$cutOff = \XF::$time - $limit['time'];
			$count = $limit['count'];

			if ($attemptRepo->countLoginAttemptsSince($cutOff, $ip, $loginParam) >= $count)
			{
				return true;
			}
		}

		return false;
	}

	protected function getLoginAttemptLimits()
	{
		return [
			['type' => 'user', 'time' => 60 * 5, 'count' => 4],
			['type' => 'user', 'time' => 60 * 30, 'count' => 8],
			['type' => 'ip',   'time' => 60 * 5, 'count' => 8],
			['type' => 'ip',   'time' => 60 * 30, 'count' => 16],
		];
	}

	protected function recordLoginAttempt($login, $ip)
	{
		if (!$ip || !$this->recordAttempts)
		{
			return;
		}

		try
		{
			$attemptRepo = $this->repository(LoginAttemptRepository::class);
			$attemptRepo->logFailedLogin($login, $ip);
		}
		catch (\Exception $e)
		{
		}
	}

	protected function clearLoginAttempts($login, $ip)
	{
		if (!$ip || !$this->recordAttempts)
		{
			return;
		}

		try
		{
			$attemptRepo = $this->repository(LoginAttemptRepository::class);
			$attemptRepo->clearLoginAttempts($login, $ip);
		}
		catch (\Exception $e)
		{
		}
	}
}
