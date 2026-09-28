<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use DBTech\Security\Repository\WatcherRepository;
use XF\PrintableException;

/**
 * @extends \XF\Entity\UserGroup
 */
class UserGroup extends XFCP_UserGroup
{
	/**
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		if ($this->isInsert())
		{
			$watcherRepo = \XF::app()->repository(WatcherRepository::class);
			$handler = $watcherRepo->getHandler('newusergroup', false);

			if ($handler)
			{
				$handler->trigger([
					'ipaddress' 	=> \XF::app()->request()->getIp(),
					'script' 		=> 'permissions',
					'action' 		=> 'added',
					'id' 			=> $this->user_group_id,
					'title' 		=> $this->title,
					'field' 		=> 'newusergroup',
					'old' 			=> '',
					'new' 			=> $this->title,
					'differences' 	=> $this->title,
				], \XF::visitor());
			}
		}

		parent::_postSave();
	}

	/**
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		$watcherRepo = \XF::app()->repository(WatcherRepository::class);
		$handler = $watcherRepo->getHandler('deletedusergroup', false);

		if ($handler)
		{
			$handler->trigger([
				'ipaddress' 	=> \XF::app()->request()->getIp(),
				'script' 		=> 'permissions',
				'action' 		=> 'deleted',
				'id' 			=> $this->user_group_id,
				'title' 		=> $this->title,
				'field' 		=> 'deletedusergroup',
				'old' 			=> $this->title,
				'new' 			=> '',
				'differences' 	=> 'usergroup id: ' . $this->user_group_id,
			], \XF::visitor());
		}

		parent::_postDelete();
	}
}