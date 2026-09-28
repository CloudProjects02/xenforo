<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\ControllerPlugin\LoginPlugin;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;

class LoginController extends AbstractController
{
	/**
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionSecurityKeyConfirm(): AbstractReply
	{
		return $this->plugin(LoginPlugin::class)->actionSecurityKeyConfirm();
	}
}