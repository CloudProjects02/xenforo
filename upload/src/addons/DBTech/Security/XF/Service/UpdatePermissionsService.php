<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Service;

use DBTech\Security\Repository\WatcherRepository;
use XF\PrintableException;

/**
 * @extends \XF\Service\UpdatePermissionsService
 */
class UpdatePermissionsService extends XFCP_UpdatePermissionsService
{
	/**
	 * @param array $values
	 *
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function updatePermissions(array $values)
	{
		if (!$this->user && !$this->userGroup)
		{
			parent::updatePermissions($values);
			return;
		}

		$field = $this->contentType ? 'forumpermissions' : 'usergrouppermissions';
		$handler = null;

		if (!$this->user)
		{
			// We are editing a user group
			$title = $this->userGroup->title;
			$id = $this->userGroup->user_group_id;
			$type = 'usergroup';

			$watcherRepo = \XF::app()->repository(WatcherRepository::class);
			$handler = $watcherRepo->getHandler($field, false);
		}
		else
		{
			// We are editing a user
			$title = $this->user->username;
			$id = $this->user->user_id;
			$type = 'user';

			$watcherRepo = \XF::app()->repository(WatcherRepository::class);
			$handler = $watcherRepo->getHandler('userpermissions', false);
		}

		if (!$handler)
		{
			parent::updatePermissions($values);
			return;
		}

		$existingGrouped = $this->getExistingPermissionEntriesGrouped();
		$permissionsGrouped = $this->getAvailablePermissionsGrouped();

		foreach ($values AS $groupId => $groupValues)
		{
			if (!is_array($groupValues) || !isset($permissionsGrouped[$groupId]))
			{
				continue;
			}

			foreach ($groupValues AS $permissionId => $value)
			{
				if (!isset($permissionsGrouped[$groupId][$permissionId]))
				{
					continue;
				}

				$permission = $permissionsGrouped[$groupId][$permissionId];

				if ($permission->permission_type == 'integer')
				{
					// Old value we are erasing
					$existingValue = isset($existingGrouped[$groupId][$permissionId])
						? $existingGrouped[$groupId][$permissionId]->permission_value_int : 0;
				}
				else
				{
					// Old value we are erasing
					$existingValue = isset($existingGrouped[$groupId][$permissionId])
						? $existingGrouped[$groupId][$permissionId]->permission_value : 'unset';
				}

				if ($existingValue != $value)
				{
					// Shorthand
					$phrase = $permission->getTitle();

					$handler->trigger([
						'ipaddress' 	=> \XF::app()->request()->getIp(),
						'script' 		=> $field,
						'action' 		=> $type,
						'id' 			=> $id,
						'title' 		=> $title,
						'field' 		=> $groupId . '_' . $permissionId,
						'old' 			=> $existingValue,
						'new' 			=> $value,
						'differences'  	=> $type . ' id: ' . $id . ' (' . $title . ') - ' . $phrase . ': ' . $existingValue . ' -> ' . $value,
					], \XF::visitor());
				}
			}
		}

		parent::updatePermissions($values);
	}
}