<?php

namespace DBTech\Security\ChangeLog;

use XF\ChangeLog\AbstractHandler;

class AdminPermissionHandler extends AbstractHandler
{
	protected array $userMap = [];

	/**
	 * @return array
	 */
	protected function getLabelMap(): array
	{
		return [
			'permissions' => 'permissions',
		];
	}

	/**
	 * @return array
	 */
	protected function getFormatterMap(): array
	{
		return [
			'permissions'                     => 'formatPermissionArray',
		];
	}

	/**
	 * @param string $permissions
	 *
	 * @return string
	 */
	protected function formatPermissionArray(string $permissions): string
	{
		$values = [];
		$ids = preg_split('/,\s+/', $permissions, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($ids AS $permission)
		{
			$values[] = \XF::phrase('admin_permission.' . $permission);
		}

		if (empty($values))
		{
			$values[] = \XF::phrase('none');
		}

		return implode(', ', $values);
	}
}