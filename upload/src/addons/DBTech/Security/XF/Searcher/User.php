<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Searcher;

/**
 * @extends \XF\Searcher\User
 */
class User extends XFCP_User
{
	public function getFormDefaults()
	{
		$previous = parent::getFormDefaults();

		$previous['Option']['use_tfa'] = [0, 1];
		$previous['Option']['dbtech_security_is_user_locked'] = [0, 1];
		$previous['Option']['dbtech_security_is_admin_locked'] = [0, 1];

		return $previous;
	}
}