<?php

namespace DBTech\Security\Admin\Controller;

use DBTech\Security\Admin\View;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;

class BreachCheckController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechSecurity');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->view(
			View\BreachCheck\IndexView::class,
			'dbtech_security_breach_check'
		);
	}
}