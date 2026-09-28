<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Admin\Controller;

use XF\Entity\User;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\Exception;

/**
 * @extends \XF\Admin\Controller\UserController
 */
class UserController extends XFCP_UserController
{
	/**
	 * @param User $user
	 *
	 * @return FormAction
	 * @throws \InvalidArgumentException
	 * @throws Exception
	 */
	protected function userSaveProcess(User $user)
	{
		$form = parent::userSaveProcess($user);

		$input = $this->filter([
			'option' => [
				'dbtech_security_is_user_locked' => 'bool',
				'dbtech_security_is_admin_locked' => 'bool',
			],
		]);

		$userOptions = $user->getRelationOrDefault('Option');
		$form->setupEntityInput($userOptions, $input['option']);

		return $form;
	}
}