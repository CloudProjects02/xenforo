<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Job;

use DBTech\Security\Entity\AccountLock;
use XF\Entity\User;
use XF\Entity\UserAuth;
use XF\Entity\UserOption;
use XF\Service\User\PasswordResetService;

/**
 * @extends \XF\Job\UserAction
 */
class UserAction extends XFCP_UserAction
{
	/**
	 * @param User $user
	 */
	protected function applyInternalUserChange(User $user)
	{
		parent::applyInternalUserChange($user);

		if ($this->getActionValue('dbtech_security_set_user_lock'))
		{
			/** @var UserOption $option */
			$option = $user->getRelationOrDefault('Option', false);

			if (!$option->use_tfa
				|| !$this->getActionValue('dbtech_security_set_user_lock_exclude_tfa')
			)
			{
				$option->dbtech_security_is_user_locked = true;
				$option->dbtech_security_is_admin_locked = false;

				$user->addCascadedSave($option);

				/** @var AccountLock $lock */
				$lock = $user->getRelationOrDefault('DBTechSecurityAccountLock', false);

				$lock->bulkSet([
					'user_id'  => $user->user_id,
					'is_staff' => $user->is_staff,
					'action'   => 'user',
					'hash'     => \md5(\XF::generateRandomString(32)),
				]);

				$user->addCascadedSave($lock);
			}
		}

		if ($this->getActionValue('dbtech_security_force_password_change')
			&& !$this->getActionValue('dbtech_security_reset_password')
		)
		{
			/** @var UserOption $option */
			$option = $user->getRelationOrDefault('Option', false);

			if (!$option->use_tfa
				|| !$this->getActionValue('dbtech_security_force_password_change_exclude_tfa')
			)
			{
				$user->dbtech_security_forcenewpass = true;
			}
		}

		/** @var UserAuth $userAuth */
		$userAuth = $user->getRelationOrDefault('Auth', false);
		if ($this->getActionValue('dbtech_security_reset_password')
			&& $user->email
		)
		{
			/** @var UserOption $option */
			$option = $user->getRelationOrDefault('Option', false);

			if (!$option->use_tfa
				|| !$this->getActionValue('dbtech_security_reset_password_exclude_tfa')
			)
			{
				$userAuth->resetPassword();
				$user->addCascadedSave($userAuth);

				$passwordReset = \XF::app()->service(PasswordResetService::class, $user);
				$passwordReset->setAdminReset(true);
				$passwordReset->triggerConfirmation();
			}
		}
	}
}