<?php

namespace DBTech\Credits\Admin\Controller;

use DBTech\Credits\Admin\View;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\Reply\AbstractReply;

class IndexController extends AbstractController
{
	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->view(
			View\IndexView::class,
			'dbtech_credits'
		);
	}
}