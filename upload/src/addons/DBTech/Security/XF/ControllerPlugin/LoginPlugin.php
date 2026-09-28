<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\ControllerPlugin;

use DBTech\Security\Repository\WatcherRepository;
use XF\Entity\User;
use XF\PrintableException;
use XF\Repository\UserTfaTrustedRepository;

/**
 * @extends \XF\ControllerPlugin\LoginPlugin
 */
class LoginPlugin extends XFCP_LoginPlugin
{
	/**
	 * @param $userId
	 *
	 * @return mixed|null
	 */
	public function setDeviceTrusted($userId)
	{
		$key = parent::setDeviceTrusted($userId);

		if (!$this->request->filter('trust_permanent', 'bool'))
		{
			return $key;
		}

		$trustedUntil = \XF::$time + 86400 * 365;
		\XF::app()->response()->setCookie('tfa_trust', $key, $trustedUntil);

		$tfaTrustRepo = \XF::app()->repository(UserTfaTrustedRepository::class);
		$record = $tfaTrustRepo->getTfaTrustRecord($userId, $key);

		$record->fastUpdate('trusted_until', $trustedUntil);

		return $key;
	}

	/**
	 * @param User $user
	 * @param $remember
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function completeLogin(User $user, $remember)
	{
		parent::completeLogin($user, $remember);

		$watcherRepo = \XF::app()->repository(WatcherRepository::class);
		$handler = $watcherRepo->getHandler('compromisedlogon', false);

		if ($handler)
		{
			$handler->trigger([
				'ipaddresses' => [$this->request->getIp()],
			], $user);
		}
	}
}