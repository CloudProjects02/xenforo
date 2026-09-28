<?php

namespace DBTech\Security\ChangeLog;

use XF\ChangeLog\AbstractHandler;
use XF\Entity\Permission;
use XF\Repository\PermissionRepository;

abstract class AbstractPermissionHandler extends AbstractHandler
{
	protected ?array $permissionsGrouped = null;

	/**
	 * @return array
	 */
	protected function getLabelMap(): array
	{
		return $this->getPermissions();
	}

	/**
	 * @return array
	 */
	protected function getFormatterMap(): array
	{
		$permissionsGrouped = $this->getPermissions();

		return array_fill_keys(array_keys($permissionsGrouped), 'formatPermission');
	}

	/**
	 * @return array
	 */
	protected function getPermissions(): array
	{
		if ($this->permissionsGrouped === null)
		{
			$permissionRepo = \XF::app()->repository(PermissionRepository::class);
			$grouped = $permissionRepo->getPermissionsGrouped();

			$this->permissionsGrouped = [];
			foreach ($grouped AS $group => $permissions)
			{
				/** @var Permission $permission */
				foreach ($permissions AS $permissionId => $permission)
				{
					$this->permissionsGrouped[$group . '_' . $permissionId] = $permission->getPhraseName();
				}
			}
		}

		return $this->permissionsGrouped;
	}

	/**
	 * @param string $permission
	 *
	 * @return string
	 */
	protected function formatPermission(string $permission): string
	{
		return match ($permission)
		{
			'unset', 'allow', 'deny', 'content_allow', 'reset' => \XF::phrase('dbtech_security_' . $permission),
			default => $permission,
		};
	}
}