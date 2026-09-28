<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Pub\View\Login;

use XF\Repository\TfaRepository;
use XF\TFA\AbstractProvider;

class PasswordConfirm extends XFCP_PasswordConfirm
{
	public function renderHtml()
	{
		if (is_callable(parent::class . '::renderHtml'))
		{
			/** @noinspection PhpUndefinedMethodInspection */
			parent::renderHtml();
		}

		$visitor = \XF::visitor();
		if ($visitor->user_id)
		{
			$providers = \XF::app()->repository(TfaRepository::class)->getAvailableProvidersForUser($visitor->user_id);
			if (isset($providers['dbtech_security_authn']))
			{
				$provider = $providers['dbtech_security_authn'];
				if (!empty($provider->options['password_confirm']))
				{
					$providerData = $provider->getUserProviderConfig($visitor->user_id);

					/** @var AbstractProvider $handler */
					$handler = $provider->handler;
					$triggerData = $handler->trigger(
						'login',
						$visitor,
						$providerData,
						\XF::app()->request()
					);

					$this->params['dbtechSecurityTfaTriggerData'] = $triggerData;
					$this->params['dbtechSecurityTfaProvider'] = $provider;
					$this->params['dbtechSecurityTfaProviderData'] = $providerData;
				}
			}
		}
	}
}