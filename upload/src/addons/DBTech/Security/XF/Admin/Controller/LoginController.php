<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Admin\Controller;

use DBTech\Security\Repository\WatcherRepository;
use XF\Entity\User;
use XF\Mvc\Reply\AbstractReply;

/**
 * @extends \XF\Admin\Controller\LoginController
 */
class LoginController extends XFCP_LoginController
{
	/**
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLogin()
	{
		$this->assertPostOnly();

		if (\XF::visitor()->user_id)
		{
			return $this->redirect($this->getDynamicRedirectIfNot('login'));
		}

		if (\XF::app()->options()->dbtechSecurityLoginCaptcha['admin']
			&& !$this->captchaIsValid(true)
		)
		{
			return $this->error(\XF::phrase('did_not_complete_the_captcha_verification_properly'));
		}

		return parent::actionLogin();
	}


	/**
	 * @param User $user
	 *
	 * @throws \Exception
	 */
	protected function completeLogin(User $user)
	{
		parent::completeLogin($user);

		$watcherRepo = \XF::app()->repository(WatcherRepository::class);
		$handler = $watcherRepo->getHandler('compromisedstafflogon', false);

		if ($handler)
		{
			$handler->trigger([
				'ipaddresses' => $this->request->getAllIps(),
			], $user);
		}
	}
}