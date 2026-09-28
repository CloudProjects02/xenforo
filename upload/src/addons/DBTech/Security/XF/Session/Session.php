<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Session;

use DBTech\Security\Repository\SessionRepository;
use XF\Db\Exception;
use XF\Entity\User;

/**
 * @extends \XF\Session\Session
 */
class Session extends XFCP_Session
{
	/**
	 * @param $ownerIp
	 * @param null $sessionId
	 *
	 * @return \XF\Session\Session
	 */
	public function start($ownerIp, $sessionId = null)
	{
		if ($sessionId)
		{
			$sessionRepo = \XF::app()->repository(SessionRepository::class);

			$_sessionId = $sessionRepo->getSessionCookie();
			if ($_sessionId)
			{
				// Check to see if we can get our session
				$session = \XF::app()->em()->find(\DBTech\Security\Entity\Session::class, $_sessionId);
				if (!$session || $session->isExpired())
				{
					// Delete session cookie
					$sessionRepo->deleteSessionCookie();

					// Create a new session for us
					return parent::start($ownerIp);
				}
			}
		}

		return parent::start($ownerIp, $sessionId);
	}

	/**
	 * @param User $user
	 *
	 * @return \XF\Session\Session
	 * @throws Exception
	 */
	public function changeUser(User $user)
	{
		parent::changeUser($user);

		$sessionRepo = \XF::app()->repository(SessionRepository::class);

		$session = null;
		$sessionId = $sessionRepo->getSessionCookie();
		if ($sessionId)
		{
			$session = \XF::app()->em()->find(\DBTech\Security\Entity\Session::class, $sessionId);
		}

		if ($session && !$session->isExpired())
		{
			$sessionRepo->updateSessionLastActive($session, $user);
		}
		else
		{
			$sessionRepo->createSession($user);
		}

		return $this;
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function save()
	{
		$sessionRepo = \XF::app()->repository(SessionRepository::class);

		$session = null;
		$sessionId = $sessionRepo->getSessionCookie();
		if ($sessionId)
		{
			$session = \XF::app()->em()->find(\DBTech\Security\Entity\Session::class, $sessionId);
		}

		if ($session && !$session->isExpired())
		{
			$sessionRepo->updateSessionLastActive($session);
		}

		return parent::save();
	}
}