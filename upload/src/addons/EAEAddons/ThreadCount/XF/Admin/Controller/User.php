<?php

namespace EAEAddons\ThreadCount\XF\Admin\Controller;

use XF\Mvc\FormAction;

class User extends XFCP_User
{
	protected function userSaveProcess(\XF\Entity\User $user)
	{
		$parent = parent::userSaveProcess($user);
		$input = $this->filter(['user' => ['eaetc_thread_count' => 'uint']]);

		return $parent->basicEntitySave($user, $input['user']);
	}
}