<?php

namespace DBTech\Security\ChangeLog;

use XF\ChangeLog\AbstractHandler;

class UserGroupHandler extends AbstractHandler
{
	/**
	 * @return array
	 */
	protected function getLabelMap(): array
	{
		return [
			'added'   => 'dbtech_security_user_group_added',
			'deleted' => 'dbtech_security_user_group_deleted',
		];
	}

	/**
	 * @return array
	 */
	protected function getFormatterMap(): array
	{
		return [];
	}
}