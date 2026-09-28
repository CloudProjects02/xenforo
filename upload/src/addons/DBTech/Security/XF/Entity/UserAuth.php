<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use DBTech\Security\Repository\PasswordRepository;

/**
 * @extends \XF\Entity\UserAuth
 */
class UserAuth extends XFCP_UserAuth
{
	public function resetPassword()
	{
		$rules = [
			'lowercase' => false,
			'uppercase' => false,
			'numbers' => false,
			'symbols' => false,
		];
		if ($this->User->hasPermission('dbtech_security', 'pwdrule_lc'))
		{
			$rules['lowercase'] = true;
		}

		if ($this->User->hasPermission('dbtech_security', 'pwdrule_uc'))
		{
			$rules['uppercase'] = true;
		}

		if ($this->User->hasPermission('dbtech_security', 'pwdrule_num'))
		{
			$rules['numbers'] = true;
		}

		if ($this->User->hasPermission('dbtech_security', 'pwdrule_sym'))
		{
			$rules['symbols'] = true;
		}

		$length = $this->User->hasPermission('dbtech_security', 'pwdrule_length');

		$repo = \XF::app()->repository(PasswordRepository::class);
		$password = $repo->generatePassword($length, $rules);

		$isReset = $this->setPassword($password);
		if ($isReset)
		{
			return $password;
		}

		return false;
	}
}