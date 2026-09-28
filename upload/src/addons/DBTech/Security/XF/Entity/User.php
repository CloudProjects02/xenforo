<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use DBTech\Security\Entity\AccountLock;
use DBTech\Security\Repository\WatcherRepository;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * @extends \XF\Entity\User
 */
class User extends XFCP_User
{
	/**
	 * @return bool
	 */
	public function hasDbtechSecurityRequiredPasswordChange()
	{
		return ($this->isDbtechSecurityPasswordExpired() || $this->hasDbtechSecurityForcedPasswordChange());
	}

	/**
	 * @return bool
	 */
	public function isDbtechSecurityPasswordExpired()
	{
		$expiry = $this->hasPermission('dbtech_security', 'pwdrule_expiry');

		return (
			$expiry > 0
			&& $this->Profile->password_date <= (\XF::$time - ($expiry * 86400))
		);
	}

	/**
	 * @return bool
	 */
	public function hasDbtechSecurityForcedPasswordChange()
	{
		return $this->dbtech_security_forcenewpass;
	}

	/**
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		$watcherRepo = \XF::app()->repository(WatcherRepository::class);
		$handler = $watcherRepo->getHandler('user', false);

		if (!$handler)
		{
			parent::_postSave();
			return;
		}

		foreach ([
			'username',
			'password',
			'email',
			'user_group_id',
			'secondary_group_ids',
		] AS $field)
		{
			if (!$this->isChanged($field))
			{
				continue;
			}

			$oldValue = $this->getPreviousValue($field);
			$newValue = $this->get($field);

			if ($field == 'password')
			{
				$oldValue = '******';
				$newValue = '******';
			}

			if (is_array($oldValue))
			{
				$oldValue = json_encode($oldValue);
			}

			if (is_array($newValue))
			{
				$newValue = json_encode($newValue);
			}

			$handler->trigger([
				'ipaddress' 	=> \XF::app()->request()->getIp(),
				'script' 		=> 'userdata',
				'action' 		=> 'edited',
				'id' 			=> $this->user_id,
				'title' 		=> $this->getPreviousValue('username'),
				'field' 		=> $field,
				'old' 			=> $oldValue,
				'new' 			=> $newValue,
				'differences' 	=> 'user id: ' . $this->user_id . ' (' . $this->getPreviousValue('username') . ') - ' . $oldValue . ' -> ' . $newValue,
			], \XF::visitor());
		}

		parent::_postSave();
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['dbtech_security_forcenewpass'] = ['type' => Entity::BOOL, 'default' => false, 'changeLog' => false];
		$structure->columns['dbtech_security_lastbreach'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];
		$structure->columns['dbtech_security_breached'] = ['type' => Entity::BOOL, 'default' => false, 'changeLog' => false];

		$structure->relations['DBTechSecurityAccountLock'] = [
			'entity' => AccountLock::class,
			'type' => self::TO_ONE,
			'conditions' => 'user_id',
			'primary' => true,
			'cascadeDelete' => true,
		];

		return $structure;
	}
}