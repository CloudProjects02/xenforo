<?php

namespace DBTech\Security\Pub\Controller;

use DBTech\Security\ControllerPlugin\LoginPlugin;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Pub\Controller\AbstractController;

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