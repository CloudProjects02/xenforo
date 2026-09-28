<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use DBTech\Security\Repository\WatcherRepository;
use XF\PrintableException;

/**
 * @extends \XF\Entity\Admin
 */
class Admin extends XFCP_Admin
{
	/**
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		parent::_postSave();

		if ($this->User && $this->isChanged('permission_cache') && $this->getOption('update_permission_entries'))
		{
			$newPermissions = [];
			foreach ($this->permission_cache AS $permissionId => $null)
			{
				$newPermissions[$permissionId] = 1;
			}

			$existingPermissions = [];
			foreach ($this->getPreviousValue('permission_cache') AS $permissionId => $null)
			{
				$existingPermissions[$permissionId] = 1;
			}

			if ($existingPermissions !== $newPermissions)
			{
				$watcherRepo = \XF::app()->repository(WatcherRepository::class);
				$handler = $watcherRepo->getHandler('adminpermissions', false);

				if ($handler)
				{
					$handler->trigger([
						'ipaddress' 	=> \XF::app()->request()->getIp(),
						'script' 		=> 'permissions',
						'action' 		=> 'edited',
						'id' 			=> $this->user_id,
						'title' 		=> $this->User->username,
						'field' 		=> 'adminpermissions',
						'old' 			=> ($existingPermissions ? implode(', ', array_keys($existingPermissions)) : ''),
						'new' 			=> ($newPermissions ? implode(', ', array_keys($newPermissions)) : ''),
						'differences' 	=> 'user id: ' . $this->user_id . ' (' . $this->User->username . ') - ' . ($existingPermissions ? implode(', ', array_keys($existingPermissions)) : \XF::phrase('dbtech_security_n_a')) . ' -> ' . ($newPermissions ? implode(', ', array_keys($newPermissions)) : \XF::phrase('dbtech_security_n_a')),
					], \XF::visitor());
				}
			}
		}
	}

	/**
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		parent::_postDelete();

		$existingPermissions = [];
		foreach ($this->getPreviousValue('permission_cache') AS $permissionId => $null)
		{
			$existingPermissions[$permissionId] = 1;
		}

		$watcherRepo = \XF::app()->repository(WatcherRepository::class);
		$handler = $watcherRepo->getHandler('adminpermissions', false);

		if ($handler)
		{
			$handler->trigger([
				'ipaddress' 	=> \XF::app()->request()->getIp(),
				'script' 		=> 'permissions',
				'action' 		=> 'edited',
				'id' 			=> $this->user_id,
				'title' 		=> $this->User->username,
				'field' 		=> 'adminpermissions',
				'old' 			=> ($existingPermissions ? implode(', ', array_keys($existingPermissions)) : ''),
				'new' 			=> '',
				'differences' 	=> 'user id: ' . $this->user_id . ' (' . $this->User->username . ') - ' . ($existingPermissions ? implode(', ', array_keys($existingPermissions)) : \XF::phrase('dbtech_security_n_a')) . ' -> ' . \XF::phrase('dbtech_security_n_a'),
			], \XF::visitor());
		}
	}
}