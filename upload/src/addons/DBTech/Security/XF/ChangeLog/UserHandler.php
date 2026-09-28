<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\ChangeLog;

/**
 * @extends \XF\ChangeLog\UserHandler
 */
class UserHandler extends XFCP_UserHandler
{
	/**
	 * @return array
	 */
	protected function getLabelMap()
	{
		$previous = parent::getLabelMap();

		$previous['dbtech_security_is_user_locked'] = 'dbtech_security_is_user_locked';
		$previous['dbtech_security_is_admin_locked'] = 'dbtech_security_is_admin_locked';

		return $previous;
	}

	/**
	 * @return array
	 */
	protected function getFormatterMap()
	{
		$previous = parent::getFormatterMap();

		$previous['dbtech_security_is_user_locked'] = 'formatYesNo';
		$previous['dbtech_security_is_admin_locked'] = 'formatYesNo';

		return $previous;
	}
}