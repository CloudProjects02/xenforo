<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Service\User;

/**
 * @extends \XF\Service\User\PasswordChangeService
 */
class PasswordChangeService extends XFCP_PasswordChangeService
{
	protected function onPasswordChange()
	{
		parent::onPasswordChange();

		$this->user->fastUpdate('dbtech_security_forcenewpass', false);
	}
}