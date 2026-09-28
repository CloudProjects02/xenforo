<?php

namespace DBTech\Security\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Service\User\TfaService;

class LoginPlugin extends AbstractPlugin
{
	/**
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionSecurityKeyConfirm(): AbstractReply
	{
		$redirect = $this->controller->getDynamicRedirectIfNot($this->buildLink('login'));
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return $this->redirect($redirect, '');
		}

		$this->assertPostOnly();

		$providerId = 'dbtech_security_authn';

		$tfaService = \XF::app()->service(TfaService::class, $visitor);
		if (!$tfaService->isProviderValid($providerId))
		{
			return $this->error(
				\XF::phrase('dbtech_security_security_key_not_registered_please_use_password')
			);
		}

		if ($tfaService->hasTooManyTfaAttempts())
		{
			return $this->error(
				\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts')
			);
		}

		$verified = $tfaService->verify($this->request, $providerId);
		if (!$verified)
		{
			return $this->error(\XF::phrase('two_step_verification_value_could_not_be_confirmed'));
		}
		else
		{
			$loginPlugin = $this->plugin(\XF\ControllerPlugin\LoginPlugin::class);
			$loginPlugin->clearTfaSessionCheck();

			$this->session()->passwordConfirm = \XF::$time;
			return $this->redirect($redirect, '');
		}
	}
}